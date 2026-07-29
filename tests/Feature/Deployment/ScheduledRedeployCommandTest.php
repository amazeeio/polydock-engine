<?php

namespace Tests\Feature\Deployment;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Models\PolydockAppInstance;
use App\Models\PolydockDeploymentRun;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Services\LagoonClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Doubles\FakeLagoonClient;
use Tests\Doubles\FakeLagoonClientService;
use Tests\TestCase;

class ScheduledRedeployCommandTest extends TestCase
{
    use RefreshDatabase;

    private FakeLagoonClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake(); // don't run the poll job
        $this->client = new FakeLagoonClient;
        $this->app->instance(LagoonClientService::class, new FakeLagoonClientService($this->client));
    }

    private function storeApp(array $attributes = []): PolydockStoreApp
    {
        $store = PolydockStore::factory()->create();

        return PolydockStoreApp::factory()->create(array_merge([
            'polydock_store_id' => $store->id,
            'redeploy_enabled' => true,
            'redeploy_interval_days' => 7,
        ], $attributes));
    }

    private function makeInstance(PolydockStoreApp $app, array $attributes = [], ?UserGroup $group = null): PolydockAppInstance
    {
        $project = $attributes['name'] ?? ('proj-'.fake()->unique()->lexify('?????'));
        $instance = new PolydockAppInstance;
        $instance->fill(array_merge([
            'polydock_store_app_id' => $app->id,
            'user_group_id' => $group?->id,
            'name' => $project,
            'app_type' => 'test-app',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
            'is_trial' => false,
        ], $attributes));
        $instance->data = ['lagoon-project-name' => $project, 'lagoon-deploy-branch' => 'main'];
        $instance->saveQuietly();

        return $instance->refresh();
    }

    private function runCommand(): int
    {
        return $this->artisan('polydock:dispatch-scheduled-redeploys')->run();
    }

    public function test_selects_only_eligible_enabled_due_non_trial_non_inflight(): void
    {
        $app = $this->storeApp();
        $disabledApp = $this->storeApp(['redeploy_enabled' => false]);

        $due = $this->makeInstance($app, ['name' => 'due']);
        $notDueYet = $this->makeInstance($app, ['name' => 'future', 'next_redeploy_at' => now()->addDays(3)]);
        $ineligible = $this->makeInstance($app, ['name' => 'ineligible', 'status' => PolydockAppInstanceStatus::PENDING_DEPLOY]);
        $trial = $this->makeInstance($app, ['name' => 'trial', 'is_trial' => true]);
        $disabled = $this->makeInstance($disabledApp, ['name' => 'disabled']);

        $runningRun = PolydockDeploymentRun::factory()->create([
            'status' => PolydockDeploymentRunStatusEnum::RUNNING,
        ]);
        $inFlight = $this->makeInstance($app, ['name' => 'inflight', 'deployment_run_id' => $runningRun->id]);

        $this->runCommand();

        // Exactly one deploy call, containing only the "due" instance.
        $this->assertCount(1, $this->client->bulkCalls);
        $projects = collect($this->client->bulkCalls[0]['environments'])->pluck('project')->all();
        $this->assertSame(['due'], $projects);

        $this->assertNotNull($due->refresh()->deployment_run_id);
        foreach ([$notDueYet, $ineligible, $trial, $disabled, $inFlight] as $skipped) {
            $this->assertTrue(
                $skipped->refresh()->deployment_run_id === null
                    || $skipped->deployment_run_id === $runningRun->id,
                "{$skipped->name} should not have been freshly deployed"
            );
        }
    }

    public function test_respects_max_per_run(): void
    {
        config(['polydock.deploy.max_per_run' => 1]);
        $app = $this->storeApp();
        $this->makeInstance($app, ['name' => 'a']);
        $this->makeInstance($app, ['name' => 'b']);

        $this->runCommand();

        $this->assertCount(1, $this->client->bulkCalls);
        $this->assertCount(1, $this->client->bulkCalls[0]['environments']);
    }

    public function test_most_outdated_instances_are_triggered_first(): void
    {
        config(['polydock.deploy.max_per_run' => 2]);
        $app = $this->storeApp();

        // never-redeployed counts as oldest, then oldest last deployment.
        $recent = $this->makeInstance($app, ['name' => 'recent', 'last_deployed_at' => now()->subDays(2)]);
        $ancient = $this->makeInstance($app, ['name' => 'ancient', 'last_deployed_at' => now()->subDays(30)]);
        $never = $this->makeInstance($app, ['name' => 'never', 'last_deployed_at' => null]);

        $this->runCommand();

        $triggered = collect($this->client->bulkCalls[0]['environments'])
            ->pluck('project')
            ->all();

        $this->assertSame(['never', 'ancient'], $triggered);
        $this->assertNull($recent->fresh()->deployment_run_id);
    }

    public function test_groups_by_store_app(): void
    {
        $app1 = $this->storeApp();
        $app2 = $this->storeApp();
        $this->makeInstance($app1, ['name' => 'a1']);
        $this->makeInstance($app2, ['name' => 'a2']);

        $this->runCommand();

        // One bulk call per store app group.
        $this->assertCount(2, $this->client->bulkCalls);
    }

    public function test_beta_group_uses_shorter_cadence(): void
    {
        $app = $this->storeApp(['redeploy_interval_days' => 7, 'beta_redeploy_interval_days' => 2]);
        $betaGroup = UserGroup::factory()->create(['is_beta' => true]);
        $normalGroup = UserGroup::factory()->create(['is_beta' => false]);

        $beta = $this->makeInstance($app, ['name' => 'beta'], $betaGroup);
        $normal = $this->makeInstance($app, ['name' => 'normal'], $normalGroup);

        $this->runCommand();

        $betaNext = $beta->refresh()->next_redeploy_at;
        $normalNext = $normal->refresh()->next_redeploy_at;

        $this->assertNotNull($betaNext);
        $this->assertNotNull($normalNext);
        // Beta (~2 days) is scheduled clearly sooner than normal (~7 days).
        $this->assertTrue($betaNext->lt($normalNext));
        $this->assertEqualsWithDelta(2, now()->diffInDays($betaNext), 0.2);
        $this->assertEqualsWithDelta(7, now()->diffInDays($normalNext), 0.2);
    }

    public function test_same_cohort_instances_get_jittered_schedules(): void
    {
        $app = $this->storeApp();
        $group = UserGroup::factory()->create(['is_beta' => false]);
        $a = $this->makeInstance($app, ['name' => 'a'], $group);
        $b = $this->makeInstance($app, ['name' => 'b'], $group);

        $this->runCommand();

        $this->assertNotEquals(
            $a->refresh()->next_redeploy_at->toDateTimeString(),
            $b->refresh()->next_redeploy_at->toDateTimeString(),
        );
    }
}
