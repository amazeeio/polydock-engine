<?php

namespace App\Console\Commands;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Jobs\EnsureUnallocatedAppInstancesJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollEnsureUnallocatedAppInstancesJobCommand extends BaseCommand
{
    protected $signature = 'polydock:poll-unallocated-instances';

    protected $description = 'Poll and dispatch jobs to ensure unallocated instances';

    private const int LOOP_DURATION = 300; // 5 minutes

    private const int SLEEP_DURATION = 5; // 5 seconds

    public function handle(): int
    {
        $endTime = now()->addSeconds(self::LOOP_DURATION);

        Log::info('Starting unallocated instances polling loop', [
            'duration' => self::LOOP_DURATION,
            'sleep' => self::SLEEP_DURATION,
        ]);

        while (now()->lt($endTime)) {
            $this->checkOnce();

            $this->info('Sleeping for '.self::SLEEP_DURATION.' seconds...');
            sleep(self::SLEEP_DURATION);
        }

        Log::info('Unallocated instances polling loop completed');

        return Command::SUCCESS;
    }

    /**
     * One poll tick: dispatch the maintenance job when any store app needs
     * pool maintenance. Returns the number of apps needing maintenance.
     */
    public function checkOnce(): int
    {
        // Eager-load the pool count in the same query: the accessor
        // short-circuits on a preloaded `unallocated_instances_count`, so the
        // per-app COUNT queries disappear. The filter block mirrors
        // PolydockStoreApp::getUnallocatedInstancesCountAttribute() (and the
        // withCount in PolydockStoreAppResource::getEloquentQuery()) — keep
        // all three in sync.
        $apps = PolydockStoreApp::query()
            ->where('status', PolydockStoreAppStatusEnum::AVAILABLE)
            ->withCount([
                'instances as unallocated_instances_count' => function ($query) {
                    $query->whereNull('user_group_id')
                        ->where(function ($q) {
                            $q->where('status', PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED)
                                ->orWhereIn('status', PolydockAppInstance::unallocatedInProgressStatuses());
                        });
                },
            ])
            ->get()
            // With the preloaded count, the accessor's deficit short-circuit
            // is query-free; its EXISTS probes only run for apps that are not
            // in deficit (over-target or refresh-configured pools).
            ->filter(fn (PolydockStoreApp $app) => $app->needs_unallocated_maintenance);

        $maintenanceCount = $apps->count();

        if ($maintenanceCount > 0) {
            Log::info('Found apps needing unallocated instance maintenance', [
                'app_count' => $maintenanceCount,
            ]);

            EnsureUnallocatedAppInstancesJob::dispatch()
                ->onQueue('unallocated-instance-creation');

            $this->info("Dispatched maintenance job for {$maintenanceCount} app(s)");
        }

        return $maintenanceCount;
    }
}
