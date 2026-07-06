<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Deploy;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\PolydockEngine\Engine;
use App\PolydockEngine\PolydockLogger;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class PollDeploymentJob extends BaseJob implements ShouldQueue
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->polydockJobStart();
        $appInstance = $this->appInstance;

        if (! $appInstance) {
            throw new Exception(
                'Failed to process PolydockAppInstance in '.class_basename(self::class).' - not found',
            );
        }

        if ($appInstance->status != PolydockAppInstanceStatus::DEPLOY_RUNNING) {
            if ($this->shouldSkipBecauseStatusAdvanced(PolydockAppInstanceStatus::DEPLOY_RUNNING)) {
                $this->polydockJobDone();

                return;
            }
        }

        try {
            // Update next poll time
            $appInstance->next_poll_after = now()->addSeconds(15);
            $appInstance->save();

            $polydockEngine = new Engine(new PolydockLogger);
            $polydockEngine->processPolydockAppInstance($appInstance);

            Log::info('Polled deployment status for app instance', [
                'app_instance_id' => $appInstance->id,
                'status' => $appInstance->status->value,
                'next_poll_after' => $appInstance->next_poll_after,
            ]);
        } catch (Exception $e) {
            Log::error('Error polling deployment status', [
                'app_instance_id' => $appInstance->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $this->polydockJobDone();
    }
}
