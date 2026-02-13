<?php

declare(strict_types=1);

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Create;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\PolydockEngine\Engine;
use App\PolydockEngine\PolydockLogger;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;
use Illuminate\Contracts\Queue\ShouldQueue;

class PreCreateJob extends BaseJob implements ShouldQueue
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

        if ($appInstance->status != PolydockAppInstanceStatus::PENDING_PRE_CREATE) {
            throw new PolydockAppInstanceStatusFlowException(
                'PreCreateJob must be in status PENDING_PRE_CREATE',
            );
        }

        $polydockEngine = new Engine(new PolydockLogger);
        $polydockEngine->processPolydockAppInstance($appInstance);

        $this->polydockJobDone();
    }
}
