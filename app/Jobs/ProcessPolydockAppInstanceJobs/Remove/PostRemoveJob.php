<?php

declare(strict_types=1);

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Remove;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Contracts\Queue\ShouldQueue;

class PostRemoveJob extends BaseJob implements ShouldQueue
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->executeTransition(PolydockAppInstanceStatus::PENDING_POST_REMOVE);
    }
}
