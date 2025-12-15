<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs;

use App\Models\PolydockAppInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\Middleware\WithoutOverlapping;

abstract class BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected PolydockAppInstance $appInstance;

    protected int $appInstanceId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $appInstanceId)
    {
        $this->appInstanceId = $appInstanceId;
    }

    public function getPolydockJobId()
    {
        $appInstance = PolydockAppInstance::find($this->appInstanceId)->refresh();

        if (!$appInstance) {
            Log::error('Failed to process PolydockAppInstance - not found', [
                'app_instance_id' => $this->appInstanceId,
                'job_type' => class_basename(static::class)
            ]);

            throw new \Exception('Failed to process PolydockAppInstance ' . $this->appInstanceId . ' - not found');
        }

        $uniqueId = "app-instance-{$appInstance->id}-job-" . class_basename(static::class);

        return $uniqueId;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job failed: ' . class_basename(static::class), [
            'app_instance_id' => $this->appInstanceId ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        try {
            $appInstance = $this->appInstance ?? PolydockAppInstance::find($this->appInstanceId);
            if ($appInstance) {
                $message = "Failed processing with the following - " . $exception->getMessage();
                $appInstance->logLine("error", $message);
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

        Log::info('Unique ID for job: ' . $uniqueId);

        return [
            (new WithoutOverlapping($uniqueId))
                ->expireAfter(5)  // 5 seconds
                ->shared() // Use shared lock across different queues
                ->dontRelease()
        ];
    }

    public function polydockJobStart()
    {
        $this->appInstance = PolydockAppInstance::find($this->appInstanceId)->refresh();
        if (!$this->appInstance) {
            Log::error('Failed to process PolydockAppInstance - not found', [
                'app_instance_id' => $this->appInstanceId,
                'job_type' => class_basename(static::class)
            ]);

            throw new \Exception('Failed to process PolydockAppInstance ' . $this->appInstanceId . ' - not found');
        }

        $uniqueId = $this->getPolydockJobId();

        Log::info('Starting to process PolydockAppInstance', [
            'job_id' => $uniqueId,
            'app_instance_id' => $this->appInstance->id,
            'store_app_id' => $this->appInstance->polydock_store_app_id,
            'store_app_name' => $this->appInstance->storeApp->name,
            'status' => $this->appInstance->status->value
        ]);
    }

    public function polydockJobDone()
    {
        if (!$this->appInstance) {
            Log::error('Failed to process PolydockAppInstance - not found', [
                'app_instance_id' => $this->appInstanceId,
                'job_type' => class_basename(static::class)
            ]);

            throw new \Exception('Failed to process PolydockAppInstance ' . $this->appInstanceId . ' - not found');
        }

        $uniqueId = $this->getPolydockJobId();

        Log::info('Finished processing PolydockAppInstance', [
            'job_id' => $uniqueId,
            'app_instance_id' => $this->appInstance->id,
            'store_app_id' => $this->appInstance->polydock_store_app_id,
            'store_app_name' => $this->appInstance->storeApp->name,
            'status' => $this->appInstance->status->value
        ]);
    }
}
