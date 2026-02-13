<?php

declare(strict_types=1);

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Deploy;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\PolydockEngine\Engine;
use App\PolydockEngine\PolydockLogger;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;
use Illuminate\Contracts\Queue\ShouldQueue;

class DeployJob extends BaseJob implements ShouldQueue
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->polydockJobStart();
        $appInstance = $this->appInstance;
        if (! $appInstance) {
            throw new \Exception(
                'Failed to process PolydockAppInstance in '.class_basename(self::class).' - not found',
            );
        }

        if ($appInstance->status != PolydockAppInstanceStatus::PENDING_DEPLOY) {
            throw new PolydockAppInstanceStatusFlowException(
                'DeployJob must be in status PENDING_DEPLOY',
            );
        }

        $polydockEngine = new Engine(new PolydockLogger);
        $polydockEngine->processPolydockAppInstance($appInstance);

        $this->polydockJobDone();
    }
}
