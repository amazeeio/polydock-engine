<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use LogicException;

class MarkStuckInstancesFailedCommand extends BaseCommand
{
    protected $signature = 'polydock:mark-stuck-instances-failed
                            {--threshold=30 : Minutes an instance can remain in a running/pending state before being considered stuck}
                            {--dry-run : Show what would be marked failed without making changes}
                            {--chunk=200 : Number of instances to process per batch}';

    protected $description = 'Detect instances stuck in intermediate statuses and mark them as failed';

    /**
     * Statuses that indicate an instance is actively progressing through the pipeline.
     * If an instance has been in one of these statuses longer than the threshold, it is stuck.
     *
     * @return array<int, PolydockAppInstanceStatus>
     */
    private static function intermediateStatuses(): array
    {
        return PolydockAppInstance::unallocatedInProgressStatuses();
    }

    /**
     * Resolve the corresponding failed status for a given intermediate status.
     */
    private static function resolveFailedStatus(PolydockAppInstanceStatus $status): PolydockAppInstanceStatus
    {
        return match ($status) {
            PolydockAppInstanceStatus::NEW,
            PolydockAppInstanceStatus::PENDING_PRE_CREATE,
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING,
            PolydockAppInstanceStatus::PRE_CREATE_COMPLETED => PolydockAppInstanceStatus::PRE_CREATE_FAILED,

            PolydockAppInstanceStatus::PENDING_CREATE,
            PolydockAppInstanceStatus::CREATE_RUNNING,
            PolydockAppInstanceStatus::CREATE_COMPLETED => PolydockAppInstanceStatus::CREATE_FAILED,

            PolydockAppInstanceStatus::PENDING_POST_CREATE,
            PolydockAppInstanceStatus::POST_CREATE_RUNNING,
            PolydockAppInstanceStatus::POST_CREATE_COMPLETED => PolydockAppInstanceStatus::POST_CREATE_FAILED,

            PolydockAppInstanceStatus::PENDING_PRE_DEPLOY,
            PolydockAppInstanceStatus::PRE_DEPLOY_RUNNING,
            PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED => PolydockAppInstanceStatus::PRE_DEPLOY_FAILED,

            PolydockAppInstanceStatus::PENDING_DEPLOY,
            PolydockAppInstanceStatus::DEPLOY_RUNNING,
            PolydockAppInstanceStatus::DEPLOY_COMPLETED => PolydockAppInstanceStatus::DEPLOY_FAILED,

            PolydockAppInstanceStatus::PENDING_POST_DEPLOY,
            PolydockAppInstanceStatus::POST_DEPLOY_RUNNING,
            PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED => PolydockAppInstanceStatus::POST_DEPLOY_FAILED,

            PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM,
            PolydockAppInstanceStatus::POLYDOCK_CLAIM_RUNNING,
            PolydockAppInstanceStatus::POLYDOCK_CLAIM_COMPLETED => PolydockAppInstanceStatus::POLYDOCK_CLAIM_FAILED,

            default => throw new LogicException("No failed status mapping for: {$status->value}"),
        };
    }

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');
        $cutoff = now()->subMinutes($threshold);

        $totalMarked = 0;
        $rows = [];

        PolydockAppInstance::query()
            ->whereIn('status', self::intermediateStatuses())
            ->where('updated_at', '<=', $cutoff)
            ->chunkById($chunkSize, function ($instances) use ($dryRun, $threshold, &$totalMarked, &$rows) {
                foreach ($instances as $instance) {
                    $failedStatus = self::resolveFailedStatus($instance->status);

                    $rows[] = [
                        $instance->id,
                        $instance->uuid ?? $instance->id,
                        $instance->status->value,
                        $failedStatus->value,
                        $instance->updated_at->diffForHumans(),
                    ];

                    if (! $dryRun) {
                        $previousStatus = $instance->status->value;

                        $instance->update([
                            'status' => $failedStatus,
                            'status_message' => "Automatically marked failed: stuck at {$previousStatus} for >{$threshold} minutes",
                        ]);
                    }

                    $totalMarked++;
                }
            });

        if ($totalMarked === 0) {
            $this->info('No stuck instances found.');
            Log::info('polydock:mark-stuck-instances-failed: no stuck instances found', [
                'threshold_minutes' => $threshold,
            ]);

            return Command::SUCCESS;
        }

        $this->info("Found {$totalMarked} stuck instance(s) (threshold: {$threshold} minutes):");
        $this->table(['ID', 'UUID', 'Was', 'Now', 'Last Updated'], $rows);

        if ($dryRun) {
            $this->warn('Dry run — no changes made.');
        } else {
            $this->info("Marked {$totalMarked} instance(s) as failed.");
            Log::warning('polydock:mark-stuck-instances-failed: marked instances as failed', [
                'count' => $totalMarked,
                'threshold_minutes' => $threshold,
            ]);
        }

        return Command::SUCCESS;
    }
}
