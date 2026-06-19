<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\EnsureUnallocatedAppInstancesJob;
use App\Models\PolydockStoreApp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MaintainPreWarmInstancesCommand extends BaseCommand
{
    protected $signature = 'polydock:maintain-prewarm-instances
                            {--app= : PolydockStoreApp UUID to limit maintenance to}
                            {--refresh-all : Queue refresh for all removable pre-warm instances instead of only stale/excess}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Queue downscale/refresh maintenance for pre-warm instances and dispatch refill maintenance';

    public function handle(): int
    {
        $appUuid = $this->option('app');
        $refreshAll = (bool) $this->option('refresh-all');

        $query = PolydockStoreApp::query();
        if ($appUuid) {
            $query->where('uuid', $appUuid);
        }

        $apps = $query->get();

        if ($apps->isEmpty()) {
            $this->info('No store apps found matching the given filters.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($apps as $app) {
            $rows[] = [
                $app->uuid,
                $app->name,
                $app->unallocated_instances_count,
                $app->target_unallocated_app_instances,
                $app->refreshableUnallocatedInstancesQuery()->count(),
            ];
        }

        $this->table(
            ['UUID', 'App', 'Unallocated', 'Target', 'Stale'],
            $rows,
        );

        if (! $this->option('force')
            && ! $this->confirm('Do you want to queue pre-warm maintenance for these apps?')) {
            $this->info('Operation cancelled by user.');

            return Command::SUCCESS;
        }

        $queuedCount = 0;

        foreach ($apps as $app) {
            $removableQuery = $app->removableUnallocatedInstancesQuery();

            if (! $refreshAll) {
                $excessCount = max(0, $app->unallocated_instances_count - $app->target_unallocated_app_instances);
                $staleCount = $app->refresh_unallocated_instances ? $app->refreshableUnallocatedInstancesQuery()->count() : 0;
                $countToQueue = min($removableQuery->count(), $excessCount + $staleCount);
            } else {
                $countToQueue = $removableQuery->count();
            }

            if ($countToQueue < 1) {
                continue;
            }

            $queued = $app->queueUnallocatedInstancesForRemoval(
                $countToQueue,
                $refreshAll ? 'CLI full pre-warm refresh requested' : 'CLI pre-warm maintenance requested',
            );

            $queuedCount += $queued;

            $this->line("Queued {$queued} pre-warm instance(s) for app {$app->name} ({$app->uuid})");
        }

        EnsureUnallocatedAppInstancesJob::dispatch()->onQueue('unallocated-instance-creation');

        $this->info("Queued {$queuedCount} pre-warm instance(s) for maintenance.");

        Log::info('Queued pre-warm instance maintenance via command', [
            'app_uuid' => $appUuid,
            'refresh_all' => $refreshAll,
            'queued_count' => $queuedCount,
        ]);

        return Command::SUCCESS;
    }
}
