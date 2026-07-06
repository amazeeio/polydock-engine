<?php

declare(strict_types=1);

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Create;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateJob extends BaseJob implements ShouldQueue
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->executeTransition(PolydockAppInstanceStatus::PENDING_CREATE);
    }
}
