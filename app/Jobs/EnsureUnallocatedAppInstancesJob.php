<?php

namespace App\Jobs;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnsureUnallocatedAppInstancesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting to maintain unallocated instances');

        $apps = PolydockStoreApp::query()
            ->where('status', PolydockStoreAppStatusEnum::AVAILABLE)
            ->get()
            ->filter(fn ($app) => $app->needs_unallocated_maintenance);

        $createdTotal = 0;
        $queuedForRemovalTotal = 0;
        $neededTotal = 0;

        $queuedForRemovalTotal += $this->refreshStaleInstances($apps);

        foreach ($apps as $app) {
            $excess = max(0, $app->unallocated_instances_count - $app->target_unallocated_app_instances);
            if ($excess > 0) {
                $queuedForRemoval = $app->queueUnallocatedInstancesForRemoval(
                    $excess,
                    'Removing excess pre-warm instance',
                );

                $queuedForRemovalTotal += $queuedForRemoval;

                Log::info('Queued excess unallocated instances for removal', [
                    'app_id' => $app->id,
                    'app_name' => $app->name,
                    'requested' => $excess,
                    'queued' => $queuedForRemoval,
                ]);
            }

            $app->refresh();
            $needed = $app->target_unallocated_app_instances - $app->unallocated_instances_count;

            if ($needed < 1) {
                continue;
            }

            // Cap the creation burst per app per pass: every new instance runs
            // a full Lagoon create+deploy, and in-progress instances count
            // toward the pool, so a large deficit fills over successive passes
            // instead of as one wall of concurrent builds.
            $needed = min($needed, max(1, (int) config('polydock.deploy.prewarm_batch', 10)));

            if (! $app->supports_pre_warming) {
                Log::info('Skipping pre-warm creation: app uses custom project naming', [
                    'app_id' => $app->id,
                    'app_name' => $app->name,
                ]);

                continue;
            }

            $neededTotal += $needed;

            Log::info('Creating unallocated instances', [
                'app_id' => $app->id,
                'app_name' => $app->name,
                'needed' => $needed,
            ]);

            // Create the needed instances
            for ($i = 0; $i < $needed; $i++) {
                Log::info('Creating unallocated instance', [
                    'app_id' => $app->id,
                    'app_name' => $app->name,
                    'needed' => $needed,
                ]);

                PolydockAppInstance::create([
                    'polydock_store_app_id' => $app->id,
                    'user_group_id' => null, // Explicitly null for clarity
                    'status' => PolydockAppInstanceStatus::PENDING_PRE_CREATE,
                    'config' => [], // Empty config for now
                ]);

                Log::info('Unallocated instance created', [
                    'app_id' => $app->id,
                    'app_name' => $app->name,
                    'needed' => $needed,
                ]);

                $createdTotal++;
            }
        }

        Log::info('Finished maintaining unallocated instances', [
            'created_total' => $createdTotal,
            'queued_for_removal_total' => $queuedForRemovalTotal,
            'needed_total' => $neededTotal,
        ]);
    }

    /**
     * Refresh stale pre-warm instances: one batch per hour globally, budget
     * shared across store apps in oldest-stale-instance-first order.
     *
     * Refreshing removes AND recreates instances — each recreation is a full
     * Lagoon deploy — so the work is staged: the hourly cache gate bounds the
     * rate, the batch cap bounds the burst, and ordering apps by their oldest
     * stale instance keeps the budget fair (an app with a large stale pool
     * cannot starve the others: whoever holds the oldest instances wins the
     * next hour's batch).
     *
     * @param  Collection<int, PolydockStoreApp>  $apps
     */
    private function refreshStaleInstances($apps): int
    {
        $refreshable = $apps
            ->filter(fn (PolydockStoreApp $app) => $app->refresh_unallocated_instances
                && $app->refreshableUnallocatedInstancesQuery()->exists())
            ->sortBy(fn (PolydockStoreApp $app) => $app->refreshableUnallocatedInstancesQuery()->min('created_at'));

        if ($refreshable->isEmpty() || ! Cache::add('prewarm-refresh-hourly-gate', true, 3600)) {
            return 0;
        }

        $budget = max(1, (int) config('polydock.deploy.prewarm_batch', 10));
        $queuedTotal = 0;

        foreach ($refreshable as $app) {
            if ($budget < 1) {
                break;
            }

            $refreshCutoff = now()->subDays($app->refresh_unallocated_instances_after_days);
            $staleCount = $app->refreshableUnallocatedInstancesQuery()->count();

            $queued = $app->queueUnallocatedInstancesForRemoval(
                min($staleCount, $budget),
                'Refreshing stale pre-warm instance',
                $refreshCutoff,
            );

            $budget -= $queued;
            $queuedTotal += $queued;

            Log::info('Queued stale unallocated instances for refresh', [
                'app_id' => $app->id,
                'app_name' => $app->name,
                'stale_count' => $staleCount,
                'queued' => $queued,
                'remaining_for_later_batches' => max(0, $staleCount - $queued),
                'refresh_after_days' => $app->refresh_unallocated_instances_after_days,
            ]);
        }

        return $queuedTotal;
    }
}
