<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Enums\PolydockDeploymentRunTriggerSourceEnum;
use App\Models\PolydockAppInstance;
use App\Services\PolydockDeploymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Selects app instances that are due for an automatic redeploy (per-store-app
 * cadence, with a shorter cadence for beta groups), triggers them in batches
 * grouped by store app, and advances each instance's next_redeploy_at.
 *
 * Throttling is a small per-run cap (default 10) on an hourly schedule: a
 * large backlog drains smoothly, oldest deployments first, rather than in a
 * spike of concurrent Lagoon builds. Trials are never included.
 */
class DispatchScheduledRedeploysCommand extends BaseCommand
{
    protected $signature = 'polydock:dispatch-scheduled-redeploys';

    protected $description = 'Trigger cadence-based Lagoon redeploys for due app instances';

    public function handle(PolydockDeploymentService $service): int
    {
        $maxPerRun = (int) config('polydock.deploy.max_per_run', 10);

        $due = $this->dueInstances($maxPerRun);

        if ($due->isEmpty()) {
            $this->info('No instances due for redeploy.');

            return Command::SUCCESS;
        }

        $triggered = 0;

        foreach ($due->groupBy('polydock_store_app_id') as $group) {
            $run = $service->redeploy($group->all(), PolydockDeploymentRunTriggerSourceEnum::SCHEDULED);

            if (! $run || $run->status === PolydockDeploymentRunStatusEnum::FAILED) {
                // Leave next_redeploy_at untouched so these retry on a later tick.
                continue;
            }

            $claimedIds = PolydockAppInstance::whereIn('id', $group->pluck('id'))
                ->where('deployment_run_id', $run->id)
                ->pluck('id')
                ->flip();

            foreach ($group as $instance) {
                // Only advance cadence for instances actually claimed by this run.
                if ($claimedIds->has($instance->id)) {
                    $this->advanceCadence($instance);
                    $triggered++;
                }
            }
        }

        $this->info("Triggered scheduled redeploy for {$triggered} instance(s).");

        return Command::SUCCESS;
    }

    /**
     * @return Collection<int, PolydockAppInstance>
     */
    private function dueInstances(int $limit): Collection
    {
        return PolydockAppInstance::query()
            ->with(['storeApp', 'userGroup'])
            ->whereIn('status', PolydockAppInstance::$redeployEligibleStatuses)
            ->where('is_trial', false)
            ->whereHas('storeApp', function ($query) {
                $query->where('redeploy_enabled', true)
                    ->whereNotNull('redeploy_interval_days');
            })
            ->whereDoesntHave('deploymentRun', function ($query) {
                $query->whereIn('status', [
                    PolydockDeploymentRunStatusEnum::PENDING->value,
                    PolydockDeploymentRunStatusEnum::RUNNING->value,
                ]);
            })
            ->where(function ($query) {
                $query->whereNull('next_redeploy_at')
                    ->orWhere('next_redeploy_at', '<=', now());
            })
            // Most-outdated first: instances never redeployed through this
            // pipeline (adopted projects, newly enabled cadence) count as
            // oldest, then by oldest last deployment. The small per-run cap
            // stages any backlog across hourly ticks instead of one burst.
            ->orderByRaw('last_deployed_at is null desc')
            ->orderBy('last_deployed_at')
            ->orderBy('next_redeploy_at')
            ->limit($limit)
            ->get();
    }

    private function advanceCadence(PolydockAppInstance $instance): void
    {
        $isBeta = (bool) $instance->userGroup?->is_beta;
        $intervalDays = $instance->storeApp->effectiveRedeployIntervalDays($isBeta);

        if ($intervalDays === null) {
            return;
        }

        // Deterministic per-instance jitter so a whole cohort does not re-converge
        // on the same tick next cycle (no randomness — keeps the schedule stable).
        $jitterWindow = max(1, (int) config('polydock.deploy.schedule_jitter_minutes', 180));
        $jitterMinutes = $instance->id % $jitterWindow;

        $instance->forceFill([
            'next_redeploy_at' => now()->addDays($intervalDays)->addMinutes($jitterMinutes),
        ])->saveQuietly();
    }
}
