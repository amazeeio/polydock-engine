<?php

namespace App\Jobs;

use App\Models\PolydockStoreApp;
use App\Models\PolydockAppInstance;
use App\Enums\PolydockStoreAppStatusEnum;
use amazeeio\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnsureUnallocatedAppInstancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting to check for apps needing unallocated instances');

        // Get all available apps that need more unallocated instances
        $apps = PolydockStoreApp::query()
            ->where('status', PolydockStoreAppStatusEnum::AVAILABLE)
            ->get()
            ->filter(fn($app) => $app->needs_more_unallocated_instances);

        $createdTotal = 0;
        $neededTotal = 0;
        foreach ($apps as $app) {
            $needed = $app->target_unallocated_app_instances - $app->unallocated_instances_count;
            $neededTotal += $needed;

            Log::info('Creating unallocated instances', [
                'app_id' => $app->id,
                'app_name' => $app->name,
                'needed' => $needed
            ]);

            // Create the needed instances
            for ($i = 0; $i < $needed; $i++) {
                Log::info('Creating unallocated instance', [
                    'app_id' => $app->id,
                    'app_name' => $app->name,
                    'needed' => $needed
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
                    'needed' => $needed
                ]);

                $createdTotal++;
            }
        }

        Log::info('Finished checking for apps needing unallocated instances', [
            'created_total' => $createdTotal,
            'needed_total' => $neededTotal
        ]);
    }
}