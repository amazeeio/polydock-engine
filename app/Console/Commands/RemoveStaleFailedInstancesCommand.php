<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Support\Facades\Log;

/**
 * Sweeps instances that died mid-lifecycle and pushes them into the removal
 * pipeline so their Lagoon projects don't accumulate forever.
 *
 * Two groups, both older than the retention window:
 * - create/deploy/claim failures -> PENDING_PRE_REMOVE (normal remove flow,
 *   app-specific pre/post-remove hooks still run)
 * - remove-stage failures -> REMOVED (the remove flow already failed once;
 *   project purge deletes the whole Lagoon project, and treats an
 *   already-gone project as success)
 *
 * Both get force_purge_requested_at stamped so the purge skips the 14-day
 * grace period — failed instances hold no user data worth a grace window.
 * PURGE_FAILED is deliberately excluded: it means the purge polling cap was
 * reached and an admin must explicitly retry.
 */
class RemoveStaleFailedInstancesCommand extends BaseCommand
{
    protected $signature = 'polydock:remove-stale-failed-instances
                          {--dry-run : List eligible instances without making changes}
                          {--days= : Override the retention window in days}
                          {--limit= : Override the per-run limit}';

    protected $description = 'Push failed app instances older than the retention window into the remove/purge pipeline';

    /**
     * Failures before or during the remove stage get the full remove flow.
     *
     * @return array<int, PolydockAppInstanceStatus>
     */
    private static function preRemovalFailedStatuses(): array
    {
        return [
            PolydockAppInstanceStatus::PRE_CREATE_FAILED,
            PolydockAppInstanceStatus::CREATE_FAILED,
            PolydockAppInstanceStatus::POST_CREATE_FAILED,
            PolydockAppInstanceStatus::PRE_DEPLOY_FAILED,
            PolydockAppInstanceStatus::DEPLOY_FAILED,
            PolydockAppInstanceStatus::POST_DEPLOY_FAILED,
            PolydockAppInstanceStatus::POLYDOCK_CLAIM_FAILED,
        ];
    }

    /**
     * Failures inside the remove stage skip straight to REMOVED so the
     * project purge can delete the whole Lagoon project.
     *
     * @return array<int, PolydockAppInstanceStatus>
     */
    private static function removeStageFailedStatuses(): array
    {
        return [
            PolydockAppInstanceStatus::PRE_REMOVE_FAILED,
            PolydockAppInstanceStatus::REMOVE_FAILED,
            PolydockAppInstanceStatus::POST_REMOVE_FAILED,
        ];
    }

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $days = (int) ($this->option('days') ?? config('polydock.cleanup.failed_instance_retention_days', 7));
        $limit = (int) ($this->option('limit') ?? config('polydock.cleanup.failed_sweep_max_per_run', 25));
        $cutoff = now()->subDays($days);

        $eligible = PolydockAppInstance::query()
            ->whereIn('status', array_merge(self::preRemovalFailedStatuses(), self::removeStageFailedStatuses()))
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        if ($eligible->isEmpty()) {
            $this->info("No failed instances older than {$days} days found.");

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d stale failed instance(s) (older than %d days).', $eligible->count(), $days));

        $swept = 0;

        foreach ($eligible as $instance) {
            $isRemoveStageFailure = in_array($instance->status, self::removeStageFailedStatuses(), true);
            $target = $isRemoveStageFailure
                ? PolydockAppInstanceStatus::REMOVED
                : PolydockAppInstanceStatus::PENDING_PRE_REMOVE;

            $line = sprintf(
                ' - %s (id=%d, %s -> %s)',
                $instance->name,
                $instance->id,
                $instance->status->value,
                $target->value,
            );

            if ($isDryRun) {
                $this->line('[dry-run]'.$line);

                continue;
            }

            $this->line($line);

            Log::info('Sweeping stale failed instance into removal pipeline', [
                'app_instance_id' => $instance->id,
                'previous_status' => $instance->status->value,
                'target_status' => $target->value,
            ]);

            $instance->force_purge_requested_at = $instance->force_purge_requested_at ?? now();
            // Status change fires PolydockAppInstanceStatusChanged, whose
            // listener dispatches the stage job / purge transition.
            $instance->setStatus($target, "Stale failed instance swept after {$days} days");
            $instance->save();

            $swept++;
        }

        if (! $isDryRun) {
            Log::info('polydock:remove-stale-failed-instances swept instances', ['count' => $swept]);
        }

        return self::SUCCESS;
    }
}
