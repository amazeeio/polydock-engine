<?php

declare(strict_types=1);

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Deploy;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Contracts\Queue\ShouldQueue;

class PreDeployJob extends BaseJob implements ShouldQueue
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->executeTransition(PolydockAppInstanceStatus::PENDING_PRE_DEPLOY);
    }
}
