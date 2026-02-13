<?php

declare(strict_types=1);

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Health;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class PollHealthJob extends BaseJob implements ShouldQueue
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

        Log::info('TODO: Implement '.class_basename(self::class));

        $this->polydockJobDone();
    }
}
