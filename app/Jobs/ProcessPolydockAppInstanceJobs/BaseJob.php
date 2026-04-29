<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs;

use App\Models\PolydockAppInstance;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

abstract class BaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected const OVERLAP_LOCK_SECONDS = 120;

    protected PolydockAppInstance $appInstance;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $appInstanceId,
    ) {}

    public function getPolydockJobId()
    {
        $appInstance = PolydockAppInstance::find($this->appInstanceId)->refresh();

        if (! $appInstance) {
            Log::error('Failed to process PolydockAppInstance - not found', [
                'app_instance_id' => $this->appInstanceId,
                'job_type' => class_basename(static::class),
            ]);

            throw new \Exception('Failed to process PolydockAppInstance '.$this->appInstanceId.' - not found');
        }

        $uniqueId = "app-instance-{$appInstance->id}-job-".class_basename(static::class);

        return $uniqueId;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job failed: '.class_basename(static::class), [
            'app_instance_id' => $this->appInstanceId ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        try {
            $appInstance = $this->appInstance ?? PolydockAppInstance::find($this->appInstanceId);
            if ($appInstance) {
                $message = 'Failed processing with the following - '.$exception->getMessage();
                $appInstance->logLine('error', $message);
            }
        } catch (\Throwable $e) {
            // Ensure the failed handler never throws
            Log::error('Error while running job failed() handler', [
                'original_error' => $exception->getMessage(),
                'handler_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        $uniqueId = $this->getPolydockJobId();

        Log::info('Unique ID for job: '.$uniqueId);

        return [
            (new WithoutOverlapping($uniqueId))
                ->expireAfter(self::OVERLAP_LOCK_SECONDS)
                ->shared() // Use shared lock across different queues
                ->dontRelease(),
        ];
    }

    protected function shouldSkipBecauseStatusAdvanced(PolydockAppInstanceStatus $expectedStatus): bool
    {
        if (! isset($this->appInstance)) {
            return false;
        }

        $currentStatus = $this->appInstance->status;

        if ($currentStatus === $expectedStatus) {
            return false;
        }

        if (! $this->isKnownStatusProgression($expectedStatus, $currentStatus)) {
            return false;
        }

        Log::info('Skipping stale lifecycle job because app instance already advanced', [
            'job_type' => class_basename(static::class),
            'app_instance_id' => $this->appInstance->id,
            'expected_status' => $expectedStatus->value,
            'current_status' => $currentStatus->value,
        ]);

        return true;
    }

    /**
     * Canonical ordering of lifecycle statuses, used to detect when a queued
     * job is stale because the instance has already advanced past the status
     * the job was scheduled for.
     *
     * Keep this list in sync with the status flow handled by
     * {@see \App\PolydockEngine\Engine}, the dispatch table in
     * {@see \App\Listeners\ProcessPolydockAppInstanceStatusChange}, and the
     * stage groupings on {@see \App\Models\PolydockAppInstance}.
     *
     * Upgrade statuses sit after the running/claimed states because an
     * in-place upgrade is initiated against an already-claimed, running
     * instance and returns to a running/claimed state when complete.
     *
     * @return list<PolydockAppInstanceStatus>
     */
    private static function lifecycleStatusOrder(): array
    {
        return [
            // New / pre-create
            PolydockAppInstanceStatus::NEW,
            PolydockAppInstanceStatus::PENDING_PRE_CREATE,
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING,
            PolydockAppInstanceStatus::PRE_CREATE_COMPLETED,
            // Create
            PolydockAppInstanceStatus::PENDING_CREATE,
            PolydockAppInstanceStatus::CREATE_RUNNING,
            PolydockAppInstanceStatus::CREATE_COMPLETED,
            // Post-create
            PolydockAppInstanceStatus::PENDING_POST_CREATE,
            PolydockAppInstanceStatus::POST_CREATE_RUNNING,
            PolydockAppInstanceStatus::POST_CREATE_COMPLETED,
            // Pre-deploy
            PolydockAppInstanceStatus::PENDING_PRE_DEPLOY,
            PolydockAppInstanceStatus::PRE_DEPLOY_RUNNING,
            PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED,
            // Deploy
            PolydockAppInstanceStatus::PENDING_DEPLOY,
            PolydockAppInstanceStatus::DEPLOY_RUNNING,
            PolydockAppInstanceStatus::DEPLOY_COMPLETED,
            // Post-deploy
            PolydockAppInstanceStatus::PENDING_POST_DEPLOY,
            PolydockAppInstanceStatus::POST_DEPLOY_RUNNING,
            PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED,
            // Claim
            PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM,
            PolydockAppInstanceStatus::POLYDOCK_CLAIM_RUNNING,
            PolydockAppInstanceStatus::POLYDOCK_CLAIM_COMPLETED,
            // Running
            PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED,
            PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
            PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
            PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE,
            // Pre-upgrade (in-place upgrade against a running, claimed instance)
            PolydockAppInstanceStatus::PENDING_PRE_UPGRADE,
            PolydockAppInstanceStatus::PRE_UPGRADE_RUNNING,
            PolydockAppInstanceStatus::PRE_UPGRADE_COMPLETED,
            // Upgrade
            PolydockAppInstanceStatus::PENDING_UPGRADE,
            PolydockAppInstanceStatus::UPGRADE_RUNNING,
            PolydockAppInstanceStatus::UPGRADE_COMPLETED,
            // Post-upgrade
            PolydockAppInstanceStatus::PENDING_POST_UPGRADE,
            PolydockAppInstanceStatus::POST_UPGRADE_RUNNING,
            PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED,
            // Pre-remove
            PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
            PolydockAppInstanceStatus::PRE_REMOVE_RUNNING,
            PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED,
            // Remove
            PolydockAppInstanceStatus::PENDING_REMOVE,
            PolydockAppInstanceStatus::REMOVE_RUNNING,
            PolydockAppInstanceStatus::REMOVE_COMPLETED,
            // Post-remove
            PolydockAppInstanceStatus::PENDING_POST_REMOVE,
            PolydockAppInstanceStatus::POST_REMOVE_RUNNING,
            PolydockAppInstanceStatus::POST_REMOVE_COMPLETED,
            PolydockAppInstanceStatus::REMOVED,
        ];
    }

    private static function lifecycleStatusIndexMap(): array
    {
        static $statusIndexMap = null;

        if ($statusIndexMap === null) {
            $statusIndexMap = [];

            foreach (self::lifecycleStatusOrder() as $index => $status) {
                $statusIndexMap[$status->value] = $index;
            }
        }

        return $statusIndexMap;
    }

    private function isKnownStatusProgression(PolydockAppInstanceStatus $expectedStatus, PolydockAppInstanceStatus $currentStatus): bool
    {
        $statusIndexMap = self::lifecycleStatusIndexMap();

        if (! array_key_exists($expectedStatus->value, $statusIndexMap) || ! array_key_exists($currentStatus->value, $statusIndexMap)) {
            return false;
        }

        return $statusIndexMap[$currentStatus->value] > $statusIndexMap[$expectedStatus->value];
    }

    public function polydockJobStart()
    {
        $this->appInstance = PolydockAppInstance::find($this->appInstanceId)->refresh();
        if (! $this->appInstance) {
            Log::error('Failed to process PolydockAppInstance - not found', [
                'app_instance_id' => $this->appInstanceId,
                'job_type' => class_basename(static::class),
            ]);

            throw new \Exception('Failed to process PolydockAppInstance '.$this->appInstanceId.' - not found');
        }

        $uniqueId = $this->getPolydockJobId();

        Log::info('Starting to process PolydockAppInstance', [
            'job_id' => $uniqueId,
            'app_instance_id' => $this->appInstance->id,
            'store_app_id' => $this->appInstance->polydock_store_app_id,
            'store_app_name' => $this->appInstance->storeApp->name,
            'status' => $this->appInstance->status->value,
        ]);
    }

    public function polydockJobDone()
    {
        if (! $this->appInstance) {
            Log::error('Failed to process PolydockAppInstance - not found', [
                'app_instance_id' => $this->appInstanceId,
                'job_type' => class_basename(static::class),
            ]);

            throw new \Exception('Failed to process PolydockAppInstance '.$this->appInstanceId.' - not found');
        }

        $uniqueId = $this->getPolydockJobId();

        Log::info('Finished processing PolydockAppInstance', [
            'job_id' => $uniqueId,
            'app_instance_id' => $this->appInstance->id,
            'store_app_id' => $this->appInstance->polydock_store_app_id,
            'store_app_name' => $this->appInstance->storeApp->name,
            'status' => $this->appInstance->status->value,
        ]);
    }
}
