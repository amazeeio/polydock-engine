<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sweeps instances that died mid-lifecycle and pushes them into the removal
 * pipeline so their Lagoon projects don't accumulate forever.
 *
 * Two groups:
 * - create/deploy/claim failures -> PENDING_PRE_REMOVE (normal remove flow,
 *   app-specific pre/post-remove hooks still run)
 * - remove-stage failures -> REMOVED (the remove flow already failed once;
 *   project purge deletes the whole Lagoon project, and treats an
 *   already-gone project as success)
 *
 * The retention window only guards instances someone owns. Allocation sets
 * user_group_id and allocation_lock in one update and only ever targets
 * RUNNING_HEALTHY_UNCLAIMED, so a failed instance with no user_group_id was
 * never handed to anyone and can no longer be claimed: it is swept on sight.
 * Claimed instances keep the full window, so a user's broken instance is not
 * deleted out from under them while it is still being looked at.
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
    private function preRemovalFailedStatuses(): array
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
    private function removeStageFailedStatuses(): array
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
            ->whereIn('status', array_merge($this->preRemovalFailedStatuses(), $this->removeStageFailedStatuses()))
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereNull('user_group_id')
                    ->orWhere('updated_at', '<=', $cutoff);
            })
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        if ($eligible->isEmpty()) {
            $this->info("No failed instances older than {$days} days found.");

            return self::SUCCESS;
        }

        $unclaimedCount = $eligible->whereNull('user_group_id')->count();

        $this->info(sprintf(
            'Found %d failed instance(s): %d unclaimed, %d claimed and older than %d days.',
            $eligible->count(),
            $unclaimedCount,
            $eligible->count() - $unclaimedCount,
            $days,
        ));

        $swept = 0;

        foreach ($eligible as $instance) {
            $isRemoveStageFailure = in_array($instance->status, $this->removeStageFailedStatuses(), true);
            $target = $isRemoveStageFailure
                ? PolydockAppInstanceStatus::REMOVED
                : PolydockAppInstanceStatus::PENDING_PRE_REMOVE;

            $line = sprintf(
                ' - %s (id=%d, %s, %s -> %s)',
                $instance->name,
                $instance->id,
                $instance->user_group_id === null ? 'unclaimed' : 'claimed',
                $instance->status->value,
                $target->value,
            );

            if ($isDryRun) {
                $this->line('[dry-run]'.$line);

                continue;
            }

            // Re-check under a row lock before writing: a retry or operator
            // may have recovered the instance between the eligibility query
            // and this write, and saving the stale snapshot would shove a
            // live instance into removal/purge.
            $sweptThisOne = DB::transaction(function () use ($instance, $cutoff, $target, $days): bool {
                $fresh = PolydockAppInstance::query()
                    ->whereKey($instance->id)
                    ->lockForUpdate()
                    ->first();

                // An instance that gained an owner since selection has to clear
                // the retention window like any other claimed instance.
                if ($fresh === null
                    || $fresh->status !== $instance->status
                    || ($fresh->user_group_id !== null && $fresh->updated_at > $cutoff)) {
                    return false;
                }

                $reason = $fresh->user_group_id === null
                    ? 'Unclaimed failed instance swept'
                    : "Stale failed instance swept after {$days} days";

                $fresh->force_purge_requested_at ??= now();
                // Status change fires PolydockAppInstanceStatusChanged, whose
                // listener dispatches the stage job / purge transition.
                $fresh->setStatus($target, $reason);
                $fresh->save();

                return true;
            });

            if (! $sweptThisOne) {
                $this->line(sprintf(' - %s (id=%d) skipped: state changed since selection', $instance->name, $instance->id));

                continue;
            }

            $this->line($line);

            Log::info('Sweeping stale failed instance into removal pipeline', [
                'app_instance_id' => $instance->id,
                'previous_status' => $instance->status->value,
                'target_status' => $target->value,
            ]);

            $swept++;
        }

        if (! $isDryRun) {
            Log::info('polydock:remove-stale-failed-instances swept instances', ['count' => $swept]);
        }

        return self::SUCCESS;
    }
}
