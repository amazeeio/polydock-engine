<?php

namespace App\Jobs;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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

            if ($app->refresh_unallocated_instances) {
                $refreshCutoff = now()->subDays($app->refresh_unallocated_instances_after_days);
                $staleCount = $app->refreshableUnallocatedInstancesQuery()->count();

                if ($staleCount > 0) {
                    $queuedForRefresh = $app->queueUnallocatedInstancesForRemoval(
                        $staleCount,
                        'Refreshing stale pre-warm instance',
                        $refreshCutoff,
                    );

                    $queuedForRemovalTotal += $queuedForRefresh;

                    Log::info('Queued stale unallocated instances for refresh', [
                        'app_id' => $app->id,
                        'app_name' => $app->name,
                        'stale_count' => $staleCount,
                        'queued' => $queuedForRefresh,
                        'refresh_after_days' => $app->refresh_unallocated_instances_after_days,
                    ]);
                }
            }

            $app->refresh();
            $needed = $app->target_unallocated_app_instances - $app->unallocated_instances_count;

            if ($needed < 1) {
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
}
