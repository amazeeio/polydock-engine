<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Jobs\EnsureUnallocatedAppInstancesJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pre-warm maintenance must not burst Lagoon: refreshes run as one small,
 * oldest-first batch per hour, and creation deficits fill in capped batches.
 */
class EnsureUnallocatedThrottlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();
        config(['polydock.deploy.prewarm_batch' => 2]);

        // Refresh-queued instances are reassigned to the default group.
        $group = UserGroup::factory()->create();
        config(['polydock.default_user_group_id_for_unallocated_instances' => $group->id]);
    }

    private function storeApp(array $appConfig = [], int $target = 0): PolydockStoreApp
    {
        return PolydockStoreApp::factory()->create([
            'polydock_store_id' => PolydockStore::factory()->create()->id,
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
            'target_unallocated_app_instances' => $target,
            'app_config' => $appConfig,
        ]);
    }

    private function seedUnclaimed(PolydockStoreApp $app, int $daysOld): PolydockAppInstance
    {
        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $app->id;
        $instance->name = 'pool-'.Str::random(6);
        $instance->app_type = 'test-app';
        $instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED;
        $instance->created_at = now()->subDays($daysOld);
        $instance->saveQuietly();

        return $instance;
    }

    public function test_refresh_runs_one_capped_oldest_first_batch_per_hour(): void
    {
        $app = $this->storeApp([
            'refresh_unallocated_instances' => true,
            'refresh_unallocated_instances_after_days' => 7,
        ], target: 5);

        $oldest = $this->seedUnclaimed($app, 30);
        $older = $this->seedUnclaimed($app, 20);
        $stale = $this->seedUnclaimed($app, 10);
        $fresh = $this->seedUnclaimed($app, 1);

        (new EnsureUnallocatedAppInstancesJob)->handle();

        // Batch of 2, oldest first; the third stale instance waits.
        $this->assertSame(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $oldest->fresh()->status);
        $this->assertSame(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $older->fresh()->status);
        $this->assertSame(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $stale->fresh()->status);
        $this->assertSame(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $fresh->fresh()->status);

        // Within the hour: the gate blocks another refresh batch.
        (new EnsureUnallocatedAppInstancesJob)->handle();
        $this->assertSame(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $stale->fresh()->status);

        // After the hour: the next batch picks up the remaining stale instance.
        $this->travel(61)->minutes();
        (new EnsureUnallocatedAppInstancesJob)->handle();
        $this->assertSame(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $stale->fresh()->status);
    }

    public function test_refresh_budget_is_shared_across_apps_oldest_first(): void
    {
        $appA = $this->storeApp([
            'refresh_unallocated_instances' => true,
            'refresh_unallocated_instances_after_days' => 7,
        ], target: 5);
        $appB = $this->storeApp([
            'refresh_unallocated_instances' => true,
            'refresh_unallocated_instances_after_days' => 7,
        ], target: 5);

        // App B holds the oldest stale instance; app A has a bigger stale pool.
        $aStale1 = $this->seedUnclaimed($appA, 20);
        $aStale2 = $this->seedUnclaimed($appA, 15);
        $bOldest = $this->seedUnclaimed($appB, 30);

        (new EnsureUnallocatedAppInstancesJob)->handle();

        // Budget of 2: app B wins first (oldest stale instance), the leftover
        // budget flows to app A — no app is starved by another's pool size.
        $this->assertSame(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $bOldest->fresh()->status);
        $this->assertSame(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $aStale1->fresh()->status);
        $this->assertSame(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $aStale2->fresh()->status);
    }

    public function test_creation_deficit_fills_in_capped_batches(): void
    {
        $app = $this->storeApp(target: 10);

        (new EnsureUnallocatedAppInstancesJob)->handle();

        // Only one batch of new instances, not the full deficit. (Freshly
        // created instances are born NEW; ProcessNewJob — faked here —
        // advances them into the create pipeline.)
        $created = PolydockAppInstance::where('polydock_store_app_id', $app->id)->count();

        $this->assertSame(2, $created);
    }
}
