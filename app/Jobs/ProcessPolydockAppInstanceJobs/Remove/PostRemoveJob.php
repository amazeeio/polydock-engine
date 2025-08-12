<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Remove;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\PolydockEngine\Engine;
use App\PolydockEngine\PolydockLogger;
use amazeeio\PolydockApp\Enums\PolydockAppInstanceStatus;
use amazeeio\PolydockApp\PolydockAppInstanceStatusFlowException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class PostRemoveJob extends BaseJob implements ShouldQueue
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

        if ($appInstance->status != PolydockAppInstanceStatus::PENDING_POST_REMOVE) {
            throw new PolydockAppInstanceStatusFlowException(
                'PostRemoveJob must be in status PENDING_POST_REMOVE'
            );
        }

        $polydockEngine = new Engine(new PolydockLogger());
        $polydockEngine->processPolydockAppInstance($appInstance);

        $this->polydockJobDone();
    }
}
