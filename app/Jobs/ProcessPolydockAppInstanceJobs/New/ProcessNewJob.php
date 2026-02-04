<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\New;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessNewJob extends BaseJob implements ShouldQueue
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->polydockJobStart();
        $appInstance = $this->appInstance;
        if (! $appInstance) {
            throw new \Exception('Failed to process PolydockAppInstance in '.class_basename(self::class).' - not found');
        }

        if ($appInstance->status != PolydockAppInstanceStatus::NEW) {
            throw new PolydockAppInstanceStatusFlowException(
                'New PolydockAppInstance must be in status NEW'
            );
        }

        $appInstance
            ->setStatus(PolydockAppInstanceStatus::PENDING_PRE_CREATE)
            ->save();

        $this->polydockJobDone();
    }
}
