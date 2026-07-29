<?php

namespace Tests\Feature\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RemoveStaleFailedInstancesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The status-change listener dispatches stage jobs; keep them queued.
        Queue::fake();
    }

    private function createInstance(
        PolydockStoreApp $storeApp,
        PolydockAppInstanceStatus $status,
        ?string $updatedAt = null,
    ): PolydockAppInstance {
        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-'.uniqid();
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'test-instance-'.uniqid();
        $instance->status = $status;
        $instance->app_type = 'test_app_type';
        $instance->data = [];
        $instance->saveQuietly();

        if ($updatedAt !== null) {
            PolydockAppInstance::where('id', $instance->id)
                ->update(['updated_at' => $updatedAt]);
            $instance->refresh();
        }

        return $instance;
    }

    private function createStoreApp(): PolydockStoreApp
    {
        $store = PolydockStore::factory()->create();

        return PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
    }

    public function test_sweeps_old_failed_instance_into_remove_flow(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance(
            $storeApp,
            PolydockAppInstanceStatus::DEPLOY_FAILED,
            now()->subDays(10)->toDateTimeString(),
        );

        $this->artisan('polydock:remove-stale-failed-instances', ['--days' => 7])
            ->assertSuccessful();

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance->status);
        $this->assertNotNull($instance->force_purge_requested_at);
    }

    public function test_remove_stage_failures_go_straight_to_removed(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance(
            $storeApp,
            PolydockAppInstanceStatus::REMOVE_FAILED,
            now()->subDays(10)->toDateTimeString(),
        );

        $this->artisan('polydock:remove-stale-failed-instances', ['--days' => 7])
            ->assertSuccessful();

        $instance->refresh();
        $this->assertNotNull($instance->force_purge_requested_at);
        $this->assertNotNull($instance->removed_at);
        // The force-purge listener path may immediately advance REMOVED to
        // PENDING_PURGE; both mean the purge pipeline now owns it.
        $this->assertContains($instance->status, [
            PolydockAppInstanceStatus::REMOVED,
            PolydockAppInstanceStatus::PENDING_PURGE,
        ]);
    }

    public function test_recent_failures_are_left_alone(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance(
            $storeApp,
            PolydockAppInstanceStatus::DEPLOY_FAILED,
            now()->subDays(2)->toDateTimeString(),
        );

        $this->artisan('polydock:remove-stale-failed-instances', ['--days' => 7])
            ->assertSuccessful();

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::DEPLOY_FAILED, $instance->status);
        $this->assertNull($instance->force_purge_requested_at);
    }

    public function test_purge_failed_is_excluded(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance(
            $storeApp,
            PolydockAppInstanceStatus::PURGE_FAILED,
            now()->subDays(30)->toDateTimeString(),
        );

        $this->artisan('polydock:remove-stale-failed-instances', ['--days' => 7])
            ->assertSuccessful();

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::PURGE_FAILED, $instance->status);
    }

    public function test_dry_run_does_not_mutate(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance(
            $storeApp,
            PolydockAppInstanceStatus::DEPLOY_FAILED,
            now()->subDays(10)->toDateTimeString(),
        );

        $this->artisan('polydock:remove-stale-failed-instances', [
            '--days' => 7,
            '--dry-run' => true,
        ])->assertSuccessful();

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::DEPLOY_FAILED, $instance->status);
        $this->assertNull($instance->force_purge_requested_at);
    }

    public function test_respects_limit(): void
    {
        $storeApp = $this->createStoreApp();
        $old = now()->subDays(10)->toDateTimeString();
        $a = $this->createInstance($storeApp, PolydockAppInstanceStatus::DEPLOY_FAILED, $old);
        $b = $this->createInstance($storeApp, PolydockAppInstanceStatus::DEPLOY_FAILED, $old);

        $this->artisan('polydock:remove-stale-failed-instances', [
            '--days' => 7,
            '--limit' => 1,
        ])->assertSuccessful();

        $statuses = collect([$a->fresh()->status, $b->fresh()->status]);
        $this->assertCount(1, $statuses->filter(
            fn ($s) => $s === PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
        ));
    }
}
