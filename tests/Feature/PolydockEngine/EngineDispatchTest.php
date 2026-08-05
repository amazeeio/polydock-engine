<?php

declare(strict_types=1);

namespace Tests\Feature\PolydockEngine;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;
use App\Polydock\Core\PolydockAppLoggerInterface;
use App\PolydockEngine\Engine;
use App\PolydockEngine\PolydockEngineAppNotFoundException;
use App\Services\LagoonClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Doubles\AlphaTestPolydockServiceProvider;
use Tests\Doubles\FakeLagoonClient;
use Tests\Doubles\FakeLagoonClientService;
use Tests\Doubles\TestLifecycleApp;
use Tests\TestCase;

/**
 * Drives Engine::processPolydockAppInstance's dispatch table with a scripted
 * app class: every PENDING_* stage, the poll stages, the failure paths, and
 * the post-create metadata push.
 */
class EngineDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Engine $engine;

    private PolydockStoreApp $storeApp;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);

        TestLifecycleApp::reset();

        $logger = Mockery::mock(PolydockAppLoggerInterface::class)->shouldIgnoreMissing();

        $this->engine = new Engine($logger, [
            AlphaTestPolydockServiceProvider::class => [
                'class' => AlphaTestPolydockServiceProvider::class,
            ],
        ]);

        $store = PolydockStore::factory()->create();
        $this->storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'polydock_app_class' => TestLifecycleApp::class,
        ]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        TestLifecycleApp::reset();
        Mockery::close();
        parent::tearDown();
    }

    private function makeInstance(PolydockAppInstanceStatus $status, ?PolydockStoreApp $storeApp = null): PolydockAppInstance
    {
        $instance = new PolydockAppInstance;
        $instance->fill([
            'polydock_store_app_id' => ($storeApp ?? $this->storeApp)->id,
            'name' => 'engine-'.Str::random(6),
            'app_type' => TestLifecycleApp::class,
            'status' => $status,
        ]);
        $instance->uuid = (string) Str::uuid();
        $instance->saveQuietly();

        return $instance;
    }

    /**
     * @return array<string, array{PolydockAppInstanceStatus, string, PolydockAppInstanceStatus}>
     */
    public static function stageDispatch(): array
    {
        return [
            'pre-create' => [PolydockAppInstanceStatus::PENDING_PRE_CREATE, 'preCreateAppInstance', PolydockAppInstanceStatus::PRE_CREATE_COMPLETED],
            'create' => [PolydockAppInstanceStatus::PENDING_CREATE, 'createAppInstance', PolydockAppInstanceStatus::CREATE_COMPLETED],
            'post-create' => [PolydockAppInstanceStatus::PENDING_POST_CREATE, 'postCreateAppInstance', PolydockAppInstanceStatus::POST_CREATE_COMPLETED],
            'pre-deploy' => [PolydockAppInstanceStatus::PENDING_PRE_DEPLOY, 'preDeployAppInstance', PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED],
            'deploy' => [PolydockAppInstanceStatus::PENDING_DEPLOY, 'deployAppInstance', PolydockAppInstanceStatus::DEPLOY_RUNNING],
            'post-deploy' => [PolydockAppInstanceStatus::PENDING_POST_DEPLOY, 'postDeployAppInstance', PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED],
            'pre-remove' => [PolydockAppInstanceStatus::PENDING_PRE_REMOVE, 'preRemoveAppInstance', PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED],
            'remove' => [PolydockAppInstanceStatus::PENDING_REMOVE, 'removeAppInstance', PolydockAppInstanceStatus::REMOVE_COMPLETED],
            'post-remove' => [PolydockAppInstanceStatus::PENDING_POST_REMOVE, 'postRemoveAppInstance', PolydockAppInstanceStatus::POST_REMOVE_COMPLETED],
            'pre-upgrade' => [PolydockAppInstanceStatus::PENDING_PRE_UPGRADE, 'preUpgradeAppInstance', PolydockAppInstanceStatus::PRE_UPGRADE_COMPLETED],
            'upgrade' => [PolydockAppInstanceStatus::PENDING_UPGRADE, 'upgradeAppInstance', PolydockAppInstanceStatus::UPGRADE_COMPLETED],
            'post-upgrade' => [PolydockAppInstanceStatus::PENDING_POST_UPGRADE, 'postUpgradeAppInstance', PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED],
            'claim' => [PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM, 'claimAppInstance', PolydockAppInstanceStatus::POLYDOCK_CLAIM_COMPLETED],
            'poll-deploy' => [PolydockAppInstanceStatus::DEPLOY_RUNNING, 'pollAppInstanceDeploymentProgress', PolydockAppInstanceStatus::DEPLOY_COMPLETED],
            'poll-upgrade' => [PolydockAppInstanceStatus::UPGRADE_RUNNING, 'pollAppInstanceUpgradeProgress', PolydockAppInstanceStatus::UPGRADE_COMPLETED],
            'poll-health-claimed' => [PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'pollAppInstanceHealthStatus', PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED],
        ];
    }

    #[DataProvider('stageDispatch')]
    public function test_each_status_dispatches_its_lifecycle_method(
        PolydockAppInstanceStatus $entry,
        string $expectedMethod,
        PolydockAppInstanceStatus $expectedFinal,
    ): void {
        $instance = $this->makeInstance($entry);

        $result = $this->engine->processPolydockAppInstance($instance);

        $this->assertSame([$expectedMethod], TestLifecycleApp::$calls);
        $this->assertSame($expectedFinal, $result->getStatus());
    }

    public function test_unclaimed_health_poll_keeps_unclaimed_status(): void
    {
        TestLifecycleApp::$statusOverrides['pollAppInstanceHealthStatus'] = PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED;
        $instance = $this->makeInstance(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED);

        $result = $this->engine->processPolydockAppInstance($instance);

        $this->assertSame(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $result->getStatus());
    }

    public function test_unknown_app_class_is_rejected(): void
    {
        $this->storeApp->updateQuietly(['polydock_app_class' => 'App\\Does\\Not\\Exist']);
        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_PRE_CREATE);

        $this->expectException(PolydockEngineAppNotFoundException::class);
        $this->engine->processPolydockAppInstance($instance->fresh());
    }

    public function test_class_not_implementing_the_interface_is_rejected(): void
    {
        $this->storeApp->updateQuietly(['polydock_app_class' => \stdClass::class]);
        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_PRE_CREATE);

        $this->expectException(PolydockEngineAppNotFoundException::class);
        $this->engine->processPolydockAppInstance($instance->fresh());
    }

    public function test_unprocessable_status_throws_flow_exception(): void
    {
        $instance = $this->makeInstance(PolydockAppInstanceStatus::NEW);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->expectExceptionMessage('not a status the engine can process');
        $this->engine->processPolydockAppInstance($instance);
    }

    public function test_lifecycle_exception_forces_failed_status_and_rethrows(): void
    {
        TestLifecycleApp::$throwOn['createAppInstance'] = new \Exception('lagoon exploded');
        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_CREATE);

        try {
            $this->engine->processPolydockAppInstance($instance);
            $this->fail('Expected a status flow exception');
        } catch (PolydockAppInstanceStatusFlowException) {
            // The failed run must leave the instance in the stage's FAILED status.
            $this->assertSame(PolydockAppInstanceStatus::CREATE_FAILED, $instance->fresh()->status);
        }
    }

    public function test_wrong_resulting_status_is_a_failure(): void
    {
        // App "succeeds" but leaves the instance in an unexpected status —
        // the engine must treat that as a failed run, not silently accept it.
        TestLifecycleApp::$statusOverrides['preCreateAppInstance'] = PolydockAppInstanceStatus::DEPLOY_COMPLETED;
        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_PRE_CREATE);

        try {
            $this->engine->processPolydockAppInstance($instance);
            $this->fail('Expected a status flow exception');
        } catch (PolydockAppInstanceStatusFlowException) {
            $this->assertSame(PolydockAppInstanceStatus::PRE_CREATE_FAILED, $instance->fresh()->status);
        }
    }

    public function test_poll_result_outside_expected_list_is_a_failure(): void
    {
        TestLifecycleApp::$statusOverrides['pollAppInstanceHealthStatus'] = PolydockAppInstanceStatus::DEPLOY_COMPLETED;
        $instance = $this->makeInstance(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED);

        $this->expectException(PolydockAppInstanceStatusFlowException::class);
        $this->engine->processPolydockAppInstance($instance);
    }

    public function test_lagoon_runtime_defaults_are_backfilled(): void
    {
        // lagoon_auto_idle / lagoon_production_environment are accessors over
        // the app_config JSON, not columns.
        $this->storeApp->updateQuietly([
            'app_config' => ['lagoon_auto_idle' => 1, 'lagoon_production_environment' => 'production'],
        ]);
        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_PRE_CREATE);

        $this->engine->processPolydockAppInstance($instance);

        $this->assertSame('1', $instance->getKeyValue('lagoon-auto-idle'));
        $this->assertSame('production', $instance->getKeyValue('lagoon-production-environment'));
    }

    public function test_existing_runtime_values_are_not_overwritten(): void
    {
        $this->storeApp->updateQuietly(['app_config' => ['lagoon_auto_idle' => 1]]);
        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_PRE_CREATE);
        $instance->storeKeyValue('lagoon-auto-idle', '0');
        $instance->saveQuietly();

        $this->engine->processPolydockAppInstance($instance);

        $this->assertSame('0', $instance->getKeyValue('lagoon-auto-idle'));
    }

    public function test_post_create_without_project_name_skips_metadata_push(): void
    {
        $fake = new FakeLagoonClient;
        $this->app->instance(LagoonClientService::class, new FakeLagoonClientService($fake));

        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_POST_CREATE);
        $this->engine->processPolydockAppInstance($instance);

        $this->assertSame([], $fake->metadataWrites);
    }

    public function test_post_create_pushes_metadata_and_skips_in_sync_keys(): void
    {
        $fake = new FakeLagoonClient;
        $fake->projects['my-project'] = [
            'id' => 42,
            // 'email' already in sync — must be skipped; firstname differs.
            'metadata' => json_encode(['email' => 'user@example.com', 'firstname' => 'Old']),
        ];
        $this->app->instance(LagoonClientService::class, new FakeLagoonClientService($fake));

        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_POST_CREATE);
        $instance->storeKeyValue('lagoon-project-name', 'my-project');
        $instance->storeKeyValue('user-email', 'user@example.com');
        $instance->storeKeyValue('user-first-name', 'Jane');
        $instance->saveQuietly();

        $this->engine->processPolydockAppInstance($instance);

        $written = collect($fake->metadataWrites)->keyBy('key');
        $this->assertFalse($written->has('email'), 'in-sync key must not be rewritten');
        $this->assertSame('Jane', $written->get('firstname')['value'] ?? null);
        $this->assertSame('dev', $written->get('polydock-env')['value'] ?? null);
    }

    public function test_metadata_push_survives_lagoon_project_lookup_failure(): void
    {
        $fake = new FakeLagoonClient;
        $fake->throwOnGetProject = true;
        $this->app->instance(LagoonClientService::class, new FakeLagoonClientService($fake));

        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_POST_CREATE);
        $instance->storeKeyValue('lagoon-project-name', 'my-project');
        $instance->storeKeyValue('user-email', 'user@example.com');
        $instance->saveQuietly();

        // "Proceeding with safety writes": the lookup failure must not abort
        // the stage — the run still succeeds and writes go through blind.
        $result = $this->engine->processPolydockAppInstance($instance);

        $this->assertSame(PolydockAppInstanceStatus::POST_CREATE_COMPLETED, $result->getStatus());
        $this->assertNotEmpty($fake->metadataWrites);
    }
}
