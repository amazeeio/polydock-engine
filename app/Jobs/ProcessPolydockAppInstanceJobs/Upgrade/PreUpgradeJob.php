<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Upgrade;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class PreUpgradeJob extends BaseJob implements ShouldQueue
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

        Log::info('TODO: Implement '.class_basename(self::class));

        $this->polydockJobDone();
    }
}
