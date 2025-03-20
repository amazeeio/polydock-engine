<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Remove;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\PolydockEngine\Engine;
use App\PolydockEngine\PolydockLogger;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class RemoveJob extends BaseJob implements ShouldQueue
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

        Log::info("TODO: Implement " . class_basename(self::class));

        $this->polydockJobDone();
    }
}
