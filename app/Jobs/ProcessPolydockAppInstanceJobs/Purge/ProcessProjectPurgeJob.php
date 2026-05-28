<?php

declare(strict_types=1);

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Purge;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\Services\LagoonProjectPurgeService;
use App\Services\PurgeResult;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Attempts a full Lagoon project deletion for an app instance whose env has
 * already been removed.
 *
 * Triggered when the instance status moves to PENDING_PURGE (set either by the
 * grace-period dispatcher or by the Filament force-delete action).
 */
class ProcessProjectPurgeJob extends BaseJob implements ShouldQueue
{
    public function handle(): void
    {
        $this->polydockJobStart();
        $appInstance = $this->appInstance;

        if (! $appInstance) {
            throw new \Exception('Failed to process PolydockAppInstance in '.class_basename(self::class).' - not found');
        }

        if ($appInstance->status !== PolydockAppInstanceStatus::PENDING_PURGE) {
            if ($this->shouldSkipBecauseStatusAdvanced(PolydockAppInstanceStatus::PENDING_PURGE)) {
                $this->polydockJobDone();

                return;
            }

            throw new PolydockAppInstanceStatusFlowException(
                'ProcessProjectPurgeJob must be in status PENDING_PURGE',
            );
        }

        $appInstance->setStatus(PolydockAppInstanceStatus::PURGE_RUNNING, 'Attempting Lagoon project purge');
        $appInstance->purge_attempts = (int) ($appInstance->purge_attempts ?? 0) + 1;
        $appInstance->purge_last_attempted_at = now();
        $appInstance->purge_failure_reason = null;
        $appInstance->save();

        $service = LagoonProjectPurgeService::makeWithDefaults();
        $maxAttempts = (int) config('polydock.cleanup.purge_max_poll_attempts', 144);

        try {
            $result = $service->attemptPurge($appInstance);
        } catch (\Throwable $e) {
            // Defensive: any unexpected error is treated as a Failed result so
            // we apply the same backoff/retry rules.
            $service->lastFailureReason = 'Unhandled exception: '.$e->getMessage();
            $result = PurgeResult::Failed;
        }

        switch ($result) {
            case PurgeResult::Purged:
            case PurgeResult::AlreadyGone:
                $appInstance->setStatus(
                    PolydockAppInstanceStatus::PURGED,
                    $result === PurgeResult::AlreadyGone
                        ? 'Lagoon project already deleted'
                        : 'Lagoon project deleted',
                );
                $appInstance->purge_failure_reason = null;
                $appInstance->save();
                // Soft-delete the row so it disappears from default queries.
                $appInstance->delete();
                break;

            case PurgeResult::StillHasEnvironments:
                // Drop back to REMOVED so the dispatcher will re-pick this up
                // on the next polling tick.
                $appInstance->purge_failure_reason = $service->lastFailureReason;
                $appInstance->setStatus(
                    PolydockAppInstanceStatus::REMOVED,
                    sprintf(
                        'Purge attempt %d/%d: %s',
                        $appInstance->purge_attempts,
                        $maxAttempts,
                        $service->lastFailureReason ?? 'still has environments',
                    ),
                );
                $appInstance->save();
                break;

            case PurgeResult::MissingProjectName:
                // Non-retryable; flag for admin attention.
                $appInstance->purge_failure_reason = $service->lastFailureReason;
                $appInstance->setStatus(
                    PolydockAppInstanceStatus::PURGE_FAILED,
                    $service->lastFailureReason ?? 'No Lagoon project name',
                );
                $appInstance->save();
                break;

            case PurgeResult::Failed:
                $appInstance->purge_failure_reason = $service->lastFailureReason;
                if ($appInstance->purge_attempts >= $maxAttempts) {
                    $appInstance->setStatus(
                        PolydockAppInstanceStatus::PURGE_FAILED,
                        sprintf(
                            'Purge failed after %d attempts: %s',
                            $appInstance->purge_attempts,
                            $service->lastFailureReason ?? 'unknown error',
                        ),
                    );
                } else {
                    // Retry on next dispatcher tick.
                    $appInstance->setStatus(
                        PolydockAppInstanceStatus::REMOVED,
                        sprintf(
                            'Purge attempt %d/%d failed: %s',
                            $appInstance->purge_attempts,
                            $maxAttempts,
                            $service->lastFailureReason ?? 'unknown error',
                        ),
                    );
                }
                $appInstance->save();
                break;
        }

        $this->polydockJobDone();
    }
}
