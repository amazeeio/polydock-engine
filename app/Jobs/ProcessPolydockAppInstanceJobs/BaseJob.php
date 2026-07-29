<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs;

use App\Listeners\ProcessPolydockAppInstanceStatusChange;
use App\Models\PolydockAppInstance;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;
use App\PolydockEngine\Engine;
use App\PolydockEngine\PolydockLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\SlackAlerts\Facades\SlackAlert;

abstract class BaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * Indicate if the job should fail when the timeout is exceeded.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    protected const OVERLAP_LOCK_SECONDS = 660;

    protected PolydockAppInstance $appInstance;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $appInstanceId,
    ) {}

    public function getPolydockJobId()
    {
        // Identity only — no DB round-trip. Existence is enforced once, in
        // polydockJobStart(), which every handle() calls first.
        return "app-instance-{$this->appInstanceId}-job-".class_basename(static::class);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job failed: '.class_basename(static::class), [
            'app_instance_id' => $this->appInstanceId ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // DB breadcrumb — best-effort and deliberately independent of the
        // Slack alert below: a database outage must not stop the page.
        try {
            $appInstance = $this->appInstance ?? PolydockAppInstance::withTrashed()->find($this->appInstanceId);
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

        // Slack page — its own fail-safe block so it survives DB outages
        // (above) and cache outages (inner catch).
        try {
            // One alert per job class per 5 minutes: a systemic outage fails
            // many jobs at once, and a flooded channel gets muted. Later
            // failures remain visible in logs and Horizon's failed-jobs tab.
            // Cache::add is atomic, so concurrent failures race safely.
            $shouldAlert = false;
            if (config('slack-alerts.webhook_urls.default')) {
                try {
                    $shouldAlert = Cache::add('slack-alert:'.class_basename(static::class), true, 300);
                } catch (\Throwable) {
                    // The dedup store being down is exactly the kind of outage
                    // this alert exists for — fail open rather than silent.
                    $shouldAlert = true;
                }
            }

            if ($shouldAlert) {
                SlackAlert::message(sprintf(
                    ':rotating_light: %s failed for app instance #%d — %s',
                    class_basename(static::class),
                    $this->appInstanceId,
                    Str::limit($exception->getMessage(), 300),
                ));
            }
        } catch (\Throwable $e) {
            // Ensure the failed handler never throws
            Log::error('Error while sending job failure alert', [
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
     * Lifecycle stage ordinals, used to detect when a queued job is stale
     * because the instance has already advanced past the *stage* the job was
     * scheduled for.
     *
     * Each logical stage of the lifecycle gets a single ordinal. All statuses
     * within the same stage (e.g. `PENDING_CREATE`, `CREATE_RUNNING`,
     * `CREATE_COMPLETED`) share that ordinal, so a stale job is only
     * considered "advanced past" when the instance has moved into a strictly
     * later stage. In-stage progression is left alone — `WithoutOverlapping`
     * handles dedup of jobs targeting the same stage.
     *
     * The four `RUNNING_*` statuses share a single ordinal because they are
     * alternative running states rather than sequential ones.
     *
     * Upgrade stages sit after the running stage because an in-place upgrade
     * is initiated against an already-claimed, running instance and returns
     * to a running/claimed state when complete.
     *
     * Keep this in sync with the status flow handled by
     * {@see Engine}, the dispatch table in
     * {@see ProcessPolydockAppInstanceStatusChange}, and the
     * stage groupings on {@see PolydockAppInstance}.
     */
    private static function lifecycleStageOrdinal(PolydockAppInstanceStatus $status): ?int
    {
        return match ($status) {
            PolydockAppInstanceStatus::NEW => 0,

            PolydockAppInstanceStatus::PENDING_PRE_CREATE,
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING,
            PolydockAppInstanceStatus::PRE_CREATE_COMPLETED => 10,

            PolydockAppInstanceStatus::PENDING_CREATE,
            PolydockAppInstanceStatus::CREATE_RUNNING,
            PolydockAppInstanceStatus::CREATE_COMPLETED => 20,

            PolydockAppInstanceStatus::PENDING_POST_CREATE,
            PolydockAppInstanceStatus::POST_CREATE_RUNNING,
            PolydockAppInstanceStatus::POST_CREATE_COMPLETED => 30,

            PolydockAppInstanceStatus::PENDING_PRE_DEPLOY,
            PolydockAppInstanceStatus::PRE_DEPLOY_RUNNING,
            PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED => 40,

            PolydockAppInstanceStatus::PENDING_DEPLOY,
            PolydockAppInstanceStatus::DEPLOY_RUNNING,
            PolydockAppInstanceStatus::DEPLOY_COMPLETED => 50,

            PolydockAppInstanceStatus::PENDING_POST_DEPLOY,
            PolydockAppInstanceStatus::POST_DEPLOY_RUNNING,
            PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED => 60,

            PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM,
            PolydockAppInstanceStatus::POLYDOCK_CLAIM_RUNNING,
            PolydockAppInstanceStatus::POLYDOCK_CLAIM_COMPLETED => 70,

            PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED,
            PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
            PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
            PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE => 80,

            PolydockAppInstanceStatus::PENDING_PRE_UPGRADE,
            PolydockAppInstanceStatus::PRE_UPGRADE_RUNNING,
            PolydockAppInstanceStatus::PRE_UPGRADE_COMPLETED => 90,

            PolydockAppInstanceStatus::PENDING_UPGRADE,
            PolydockAppInstanceStatus::UPGRADE_RUNNING,
            PolydockAppInstanceStatus::UPGRADE_COMPLETED => 100,

            PolydockAppInstanceStatus::PENDING_POST_UPGRADE,
            PolydockAppInstanceStatus::POST_UPGRADE_RUNNING,
            PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED => 110,

            PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
            PolydockAppInstanceStatus::PRE_REMOVE_RUNNING,
            PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED => 120,

            PolydockAppInstanceStatus::PENDING_REMOVE,
            PolydockAppInstanceStatus::REMOVE_RUNNING,
            PolydockAppInstanceStatus::REMOVE_COMPLETED => 130,

            PolydockAppInstanceStatus::PENDING_POST_REMOVE,
            PolydockAppInstanceStatus::POST_REMOVE_RUNNING,
            PolydockAppInstanceStatus::POST_REMOVE_COMPLETED => 140,

            PolydockAppInstanceStatus::REMOVED => 150,

            PolydockAppInstanceStatus::PENDING_PURGE => 160,

            PolydockAppInstanceStatus::PURGE_RUNNING => 170,

            PolydockAppInstanceStatus::PURGE_FAILED => 180,

            default => null,
        };
    }

    private function isKnownStatusProgression(PolydockAppInstanceStatus $expectedStatus, PolydockAppInstanceStatus $currentStatus): bool
    {
        $expectedOrdinal = self::lifecycleStageOrdinal($expectedStatus);
        $currentOrdinal = self::lifecycleStageOrdinal($currentStatus);

        if ($expectedOrdinal === null || $currentOrdinal === null) {
            return false;
        }

        return $currentOrdinal > $expectedOrdinal;
    }

    public function polydockJobStart()
    {
        // Single fetch for the whole job: find() already returns a fresh row
        // (no refresh needed) and storeApp is eager-loaded for the log lines
        // here and in polydockJobDone().
        $appInstance = PolydockAppInstance::withTrashed()
            ->with('storeApp')
            ->find($this->appInstanceId);
        if (! $appInstance) {
            Log::error('Failed to process PolydockAppInstance - not found', [
                'app_instance_id' => $this->appInstanceId,
                'job_type' => class_basename(static::class),
            ]);

            throw new \Exception('Failed to process PolydockAppInstance '.$this->appInstanceId.' - not found');
        }

        $this->appInstance = $appInstance;

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

    /**
     * Execute the standard lifecycle stage transition logic.
     */
    protected function executeTransition(PolydockAppInstanceStatus $expectedStatus): void
    {
        $this->polydockJobStart();

        $appInstance = $this->appInstance;

        if ($appInstance->status !== $expectedStatus) {
            if ($this->shouldSkipBecauseStatusAdvanced($expectedStatus)) {
                $this->polydockJobDone();

                return;
            }

            throw new PolydockAppInstanceStatusFlowException(
                class_basename(static::class).' must be in status '.$expectedStatus->name
            );
        }

        $polydockEngine = new Engine(new PolydockLogger);
        $polydockEngine->processPolydockAppInstance($appInstance);

        $this->polydockJobDone();
    }
}
