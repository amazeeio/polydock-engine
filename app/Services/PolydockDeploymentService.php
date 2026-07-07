<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Enums\PolydockDeploymentRunTriggerSourceEnum;
use App\Jobs\PollDeploymentRunJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockDeploymentRun;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Single entry point for triggering and tracking Lagoon redeploys ("upgrade
 * rollouts") across app instances. Both the scheduled cadence dispatcher and the
 * admin bulk action call this so there is exactly one deploy code path.
 *
 * Rollouts are plain redeploy-latest: instances stay in their RUNNING_HEALTHY_*
 * status and deploy state is tracked on PolydockDeploymentRun + cached instance
 * columns. They do NOT route through the PRE/UPGRADE/POST_UPGRADE state machine.
 */
class PolydockDeploymentService
{
    public function __construct(private LagoonClientService $lagoon) {}

    /**
     * Trigger a redeploy across the given instances and return the created run.
     *
     * Ineligible instances (wrong status) and instances with an in-flight deploy
     * are skipped. Returns null when nothing is left to deploy.
     *
     * @param  iterable<PolydockAppInstance>  $instances
     */
    public function redeploy(
        iterable $instances,
        PolydockDeploymentRunTriggerSourceEnum $source,
        ?User $triggeredBy = null,
        bool $variablesOnly = false,
    ): ?PolydockDeploymentRun {
        /** @var Collection<int, PolydockAppInstance> $deployable */
        $deployable = collect($instances)
            ->filter(fn (PolydockAppInstance $i) => $i->isRedeployEligible() && ! $i->hasInFlightDeployment())
            ->values();

        // Build Lagoon environment tuples + a lookup back to the instance.
        $environments = [];
        $byTarget = [];
        $skippedMissingTarget = 0;

        foreach ($deployable as $instance) {
            $project = $instance->getKeyValue('lagoon-project-name');
            $branch = $instance->getKeyValue('lagoon-deploy-branch');

            if (empty($project) || empty($branch)) {
                $skippedMissingTarget++;

                continue;
            }

            $environments[] = ['project' => $project, 'name' => $branch];
            $byTarget[$this->targetKey($project, $branch)] = $instance;
        }

        if (empty($environments)) {
            Log::info('Redeploy requested but no deployable instances', [
                'trigger_source' => $source->value,
                'requested' => $deployable->count(),
                'skipped_missing_target' => $skippedMissingTarget,
            ]);

            return null;
        }

        $storeAppIds = collect($byTarget)->map(fn ($i) => $i->polydock_store_app_id)->unique();

        $run = PolydockDeploymentRun::create([
            'polydock_store_app_id' => $storeAppIds->count() === 1 ? $storeAppIds->first() : null,
            'triggered_by_user_id' => $triggeredBy?->id,
            'trigger_source' => $source,
            'status' => PolydockDeploymentRunStatusEnum::RUNNING,
            'total_count' => count($environments),
            'started_at' => now(),
        ]);

        $buildVariables = $variablesOnly ? ['LAGOON_VARIABLES_ONLY' => 'true'] : [];

        try {
            $client = $this->lagoon->getAuthenticatedClient();
            $result = $client->bulkDeployEnvironments(
                environments: $environments,
                name: 'Polydock redeploy '.$run->uuid,
                buildVariables: $buildVariables,
            );
        } catch (Throwable $e) {
            Log::error('Redeploy trigger failed', ['run' => $run->uuid, 'error' => $e->getMessage()]);
            $this->failRun($run, 'Trigger failed: '.$e->getMessage());

            return $run;
        }

        if (isset($result['error'])) {
            $error = is_array($result['error']) ? json_encode($result['error']) : (string) $result['error'];
            Log::error('Redeploy trigger returned error', ['run' => $run->uuid, 'error' => $error]);
            $this->failRun($run, 'Trigger error: '.$error);

            return $run;
        }

        $bulkId = $result['bulkDeployEnvironmentLatest'] ?? null;
        $run->lagoon_bulk_id = $bulkId;
        $run->save();

        // Only now claim the instances for this run, so a failed trigger never
        // leaves them looking permanently in-flight.
        foreach ($byTarget as $instance) {
            $instance->forceFill([
                'deployment_run_id' => $run->id,
                'last_deploy_triggered_at' => now(),
            ])->saveQuietly();
        }

        PollDeploymentRunJob::dispatch($run->id);

        Log::info('Redeploy triggered', [
            'run' => $run->uuid,
            'bulk_id' => $bulkId,
            'count' => count($environments),
            'trigger_source' => $source->value,
        ]);

        return $run->refresh();
    }

    /**
     * Poll a single run's Lagoon bulk deployment once, updating per-instance cached
     * deploy state and the run's roll-up counts/status. Safe to call repeatedly.
     */
    public function pollRun(PolydockDeploymentRun $run): void
    {
        if ($run->isTerminal()) {
            return;
        }

        $run->forceFill([
            'last_polled_at' => now(),
            'poll_attempts' => $run->poll_attempts + 1,
        ])->save();

        if (empty($run->lagoon_bulk_id)) {
            $this->failRun($run, 'No Lagoon bulk id recorded for run');

            return;
        }

        try {
            $client = $this->lagoon->getAuthenticatedClient();
            $deployments = $client->getDeploymentsByBulkId($run->lagoon_bulk_id);
        } catch (Throwable $e) {
            Log::warning('Redeploy poll failed', ['run' => $run->uuid, 'error' => $e->getMessage()]);
            $this->giveUpIfExhausted($run, 'Polling failed: '.$e->getMessage());

            return;
        }

        if (isset($deployments['error'])) {
            $this->giveUpIfExhausted($run, 'Poll error from Lagoon');

            return;
        }

        // Index the latest deployment per environment target.
        $latestByTarget = [];
        foreach ($deployments as $deployment) {
            $project = data_get($deployment, 'environment.project.name');
            $branch = data_get($deployment, 'environment.name');
            if (! $project || ! $branch) {
                continue;
            }
            $latestByTarget[$this->targetKey($project, $branch)] = $deployment;
        }

        $success = 0;
        $failed = 0;
        $allTerminal = true;

        foreach ($run->instances as $instance) {
            $project = $instance->getKeyValue('lagoon-project-name');
            $branch = $instance->getKeyValue('lagoon-deploy-branch');
            $deployment = $latestByTarget[$this->targetKey((string) $project, (string) $branch)] ?? null;

            if (! $deployment) {
                $allTerminal = false;

                continue;
            }

            $status = strtolower((string) data_get($deployment, 'status'));
            $attributes = [
                'last_deployment_name' => data_get($deployment, 'name'),
                'last_deployment_status' => $status,
            ];

            if ($this->isSuccessStatus($status)) {
                $success++;
                $attributes['last_deployed_at'] = data_get($deployment, 'completed') ?: now();
            } elseif ($this->isFailedStatus($status)) {
                $failed++;
            } else {
                $allTerminal = false;
            }

            $instance->forceFill($attributes)->saveQuietly();
        }

        $run->forceFill([
            'success_count' => $success,
            'failed_count' => $failed,
        ])->save();

        if ($allTerminal) {
            $this->finalizeRun($run, $success, $failed);

            return;
        }

        $this->giveUpIfExhausted($run, 'Deployments did not reach a terminal state in time');
    }

    private function finalizeRun(PolydockDeploymentRun $run, int $success, int $failed): void
    {
        $status = match (true) {
            $failed === 0 => PolydockDeploymentRunStatusEnum::COMPLETED,
            $success === 0 => PolydockDeploymentRunStatusEnum::FAILED,
            default => PolydockDeploymentRunStatusEnum::PARTIAL_FAILED,
        };

        $run->forceFill([
            'status' => $status,
            'completed_at' => now(),
        ])->save();
    }

    private function giveUpIfExhausted(PolydockDeploymentRun $run, string $reason): void
    {
        $max = (int) config('polydock.deploy.max_poll_attempts', 144);
        if ($run->poll_attempts >= $max) {
            Log::warning('Giving up polling redeploy run', ['run' => $run->uuid, 'reason' => $reason]);
            $this->failRun($run, $reason);
        }
    }

    private function failRun(PolydockDeploymentRun $run, string $reason): void
    {
        $run->forceFill([
            'status' => PolydockDeploymentRunStatusEnum::FAILED,
            'completed_at' => now(),
        ])->save();

        Log::warning('Redeploy run marked failed', ['run' => $run->uuid, 'reason' => $reason]);
    }

    private function targetKey(string $project, string $branch): string
    {
        return $project.'|'.$branch;
    }

    private function isSuccessStatus(string $status): bool
    {
        return in_array($status, ['complete', 'completed', 'success'], true);
    }

    private function isFailedStatus(string $status): bool
    {
        return in_array($status, ['failed', 'failure', 'error', 'deployerror', 'cancelled', 'canceled'], true);
    }
}
