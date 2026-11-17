<?php

declare(strict_types=1);

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Trial;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Support\Facades\Log;

class ProcessTrialCompleteStageRemovalJob extends BaseJob
{
    /** @var list<PolydockAppInstanceStatus> */
    public const array REMOVABLE_STATUSES = [
        PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
        PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE,
    ];

    public function handle(): void
    {
        $this->polydockJobStart();

        if (
            ! \in_array($this->appInstance->status, self::REMOVABLE_STATUSES, true)
            || ! $this->appInstance->isTrialExpired()
        ) {
            $this->appInstance->info('Trial complete stage removal not initiated - instance not in correct status', [
                'app_instance_id' => $this->appInstance->id,
                'current_status' => $this->appInstance->status,
                'required_statuses' => self::REMOVABLE_STATUSES,
            ]);

            Log::info('Trial complete stage removal not initiated - instance not in correct status', [
                'app_instance_id' => $this->appInstance->id,
                'current_status' => $this->appInstance->status,
                'required_statuses' => self::REMOVABLE_STATUSES,
            ]);

            return;
        }

        Log::info('Trial complete stage removal initiated', [
            'app_instance_id' => $this->appInstance->id,
        ]);

        $previousStatus = $this->appInstance->status;

        $this->appInstance->update([
            'trial_completed' => true,
        ]);

        // Set status to pending pre-remove
        $this->appInstance
            ->setStatus(
                PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
                'Trial completed, initiating removal process',
            )
            ->save();

        $this->appInstance->info('Trial complete stage removal initiated', [
            'app_instance_id' => $this->appInstance->id,
            'previous_status' => $previousStatus,
            'new_status' => PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
        ]);

        Log::info('Trial complete stage removal initiated', [
            'app_instance_id' => $this->appInstance->id,
        ]);

        $this->polydockJobDone();
    }
}
