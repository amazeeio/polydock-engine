<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Apps\Generic\PolydockAiApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\PolydockEngine\PolydockLogger;
use App\Services\ClaimExistingProjectService;
use App\Services\LagoonClientService;
use App\Services\LagoonProjectPurgeService;
use App\Services\PurgeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Doubles\FakeLagoonClient;
use Tests\Doubles\FakeLagoonClientService;
use Tests\TestCase;

class ClaimExistingProjectTest extends TestCase
{
    use RefreshDatabase;

    private FakeLagoonClient $client;

    private PolydockStoreApp $storeApp;

    private UserGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // keep the create/deploy pipeline from running against the adopted project

        $this->client = new FakeLagoonClient;
        $this->app->instance(LagoonClientService::class, new FakeLagoonClientService($this->client));

        // lagoon_deploy_group_name lives on the store; the store app reads it via accessor.
        $store = PolydockStore::factory()->create([
            'lagoon_deploy_group_name' => 'polydock-deployers',
        ]);
        $this->storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            // Claiming requires a redeploy-capable store app — the scheduled
            // redeploy is the stated point of adopting a project.
            'redeploy_enabled' => true,
            'redeploy_interval_days' => 7,
        ]);
        $this->group = UserGroup::factory()->create();
    }

    private function service(): ClaimExistingProjectService
    {
        return app(ClaimExistingProjectService::class);
    }

    public function test_claims_existing_project_and_lands_healthy_claimed(): void
    {
        $this->client->registerProject('acme-site', id: 42, productionEnvironment: 'production', openshiftId: 9);

        $instance = $this->service()->claim($this->storeApp, $this->group, 'acme-site');

        $this->assertSame(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $instance->status);
        $this->assertTrue($instance->isRedeployEligible());

        // Identity points at the real project, not a generated one.
        $this->assertSame('acme-site', $instance->name);
        $this->assertSame('acme-site', $instance->getKeyValue('lagoon-project-name'));
        $this->assertSame(42, $instance->getKeyValue('lagoon-project-id'));
        $this->assertSame('production', $instance->getKeyValue('lagoon-deploy-branch'));
        $this->assertSame(9, $instance->getKeyValue('lagoon-deploy-region-id'));
        $this->assertTrue((bool) $instance->getKeyValue('adopted'));
        $this->assertSame($this->group->id, $instance->user_group_id);

        // Polydock's deploy group was granted access to the existing project.
        $this->assertSame(
            [['group' => 'polydock-deployers', 'project' => 'acme-site']],
            $this->client->groupAdds,
        );
    }

    public function test_unknown_project_is_rejected_and_creates_nothing(): void
    {
        try {
            $this->service()->claim($this->storeApp, $this->group, 'does-not-exist');
            $this->fail('Expected claim of an unknown project to throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not found', $e->getMessage());
        }

        $this->assertSame(0, PolydockAppInstance::count());
        $this->assertSame([], $this->client->groupAdds); // no access granted on failure
    }

    public function test_double_claim_is_rejected(): void
    {
        $this->client->registerProject('acme-site');
        $this->service()->claim($this->storeApp, $this->group, 'acme-site');

        $this->expectException(\RuntimeException::class);
        $this->service()->claim($this->storeApp, $this->group, 'acme-site');
    }

    public function test_instance_name_matches_real_project_even_on_name_collision(): void
    {
        // A pre-existing instance already occupies the display name (but tracks a
        // different project), so the creating hook would suffix the adopted name.
        $collider = new PolydockAppInstance;
        $collider->polydock_store_app_id = $this->storeApp->id;
        $collider->user_group_id = $this->group->id;
        $collider->name = 'acme-site';
        $collider->app_type = 'test-app';
        $collider->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $collider->data = ['lagoon-project-name' => 'some-other-project'];
        $collider->saveQuietly();

        $this->client->registerProject('acme-site');
        $instance = $this->service()->claim($this->storeApp, $this->group, 'acme-site');

        // Display name and Lagoon identity must agree.
        $this->assertSame('acme-site', $instance->name);
        $this->assertSame('acme-site', $instance->getKeyValue('lagoon-project-name'));
    }

    public function test_concurrent_claim_is_rejected_while_lock_held(): void
    {
        $this->client->registerProject('acme-site');

        $lock = Cache::lock('claim-lagoon-project:acme-site', 30);
        $this->assertTrue($lock->get());

        try {
            $this->service()->claim($this->storeApp, $this->group, 'acme-site');
            $this->fail('Expected a concurrent claim to be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already in progress', $e->getMessage());
        } finally {
            $lock->release();
        }

        $this->assertSame(0, PolydockAppInstance::count());
        $this->assertSame([], $this->client->groupAdds); // no access granted while blocked
    }

    public function test_adopted_removal_pipeline_detaches_without_touching_lagoon(): void
    {
        $this->client->registerProject('acme-site');
        $instance = $this->service()->claim($this->storeApp, $this->group, 'acme-site');

        // The app object has no Lagoon client wired in. If any guard sat after
        // the Lagoon ping/validation — or if post-remove still wrote its
        // POLYDOCK_APP_REMOVED_* markers — these calls would throw. Reaching
        // *_COMPLETED proves the adopted detach is fully local and never mutates
        // the live Lagoon project. Entry is PENDING_PRE_REMOVE — the status the
        // real removal flow dispatches first — so the whole pipeline is covered,
        // not just the tail stages.
        $app = new PolydockAiApp('claim-test', 'desc', 'author', 'https://example.com', 'a@example.com');

        $instance->setStatus(PolydockAppInstanceStatus::PENDING_PRE_REMOVE)->save();
        $app->preRemoveAppInstance($instance);
        $this->assertSame(PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED, $instance->status);

        $instance->setStatus(PolydockAppInstanceStatus::PENDING_REMOVE)->save();
        $app->removeAppInstance($instance);
        $this->assertSame(PolydockAppInstanceStatus::REMOVE_COMPLETED, $instance->status);

        $instance->setStatus(PolydockAppInstanceStatus::PENDING_POST_REMOVE)->save();
        $app->postRemoveAppInstance($instance);
        $this->assertSame(PolydockAppInstanceStatus::POST_REMOVE_COMPLETED, $instance->status);
    }

    public function test_group_grant_rejection_fails_claim_and_creates_nothing(): void
    {
        $this->client->registerProject('acme-site');
        $this->client->addGroupResponse = ['error' => [['message' => 'group does not exist']]];

        try {
            $this->service()->claim($this->storeApp, $this->group, 'acme-site');
            $this->fail('Expected a rejected group grant to fail the claim.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Failed to add group', $e->getMessage());
        }

        $this->assertSame(0, PolydockAppInstance::count());
    }

    public function test_redeploy_disabled_store_app_is_rejected(): void
    {
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $this->storeApp->polydock_store_id,
            'redeploy_enabled' => false,
        ]);
        $this->client->registerProject('acme-site');

        try {
            $this->service()->claim($storeApp, $this->group, 'acme-site');
            $this->fail('Expected claim against a redeploy-disabled store app to throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('redeploys disabled', $e->getMessage());
        }

        $this->assertSame(0, PolydockAppInstance::count());
        $this->assertSame([], $this->client->groupAdds); // rejected before any Lagoon mutation
    }

    public function test_purge_never_deletes_an_adopted_project(): void
    {
        $this->client->registerProject('acme-site');
        $instance = $this->service()->claim($this->storeApp, $this->group, 'acme-site');

        $purge = new LagoonProjectPurgeService(new PolydockLogger, $this->client);
        $result = $purge->attemptPurge($instance);

        // AlreadyGone => Polydock record is cleaned up, real Lagoon project untouched.
        $this->assertSame(PurgeResult::AlreadyGone, $result);
    }
}
