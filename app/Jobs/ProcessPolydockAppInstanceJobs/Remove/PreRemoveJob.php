<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Remove;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\PolydockEngine\Engine;
use App\PolydockEngine\PolydockLogger;
use amazeeio\PolydockApp\Enums\PolydockAppInstanceStatus;
use amazeeio\PolydockApp\PolydockAppInstanceStatusFlowException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class PreRemoveJob extends BaseJob implements ShouldQueue
{

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->polydockJobStart();
        $appInstance = $this->appInstance;
        if(!$appInstance) {
            throw new \Exception('Failed to process PolydockAppInstance in ' . class_basename(self::class) . ' - not found');
        }

        if ($appInstance->status != PolydockAppInstanceStatus::PENDING_PRE_REMOVE) {
            throw new PolydockAppInstanceStatusFlowException(
                'PreRemoveJob must be in status PENDING_PRE_REMOVE'
            );
        }

        $polydockEngine = new Engine(new PolydockLogger());
        $polydockEngine->processPolydockAppInstance($appInstance);

        $this->polydockJobDone();
    }
}
