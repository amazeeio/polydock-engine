<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs\Trial;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTrialCompleteStageRemovalJob extends BaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $this->polydockJobStart();

        if ($this->appInstance->status !== PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED
            || !$this->appInstance->isTrialExpired()
        ) {
            $this->appInstance->info('Trial complete stage removal not initiated - instance not in correct status', [
                'app_instance_id' => $this->appInstance->id,
                'current_status' => $this->appInstance->status,
                'required_status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
            ]);

            Log::info('Trial complete stage removal not initiated - instance not in correct status', [
                'app_instance_id' => $this->appInstance->id,
                'current_status' => $this->appInstance->status,
                'required_status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
            ]);

            return;
        }

        Log::info('Trial complete stage removal initiated', [
            'app_instance_id' => $this->appInstance->id,
        ]);

        $this->appInstance->update([
            'trial_completed' => TRUE,
        ]);

        // Set status to pending pre-remove
        $this->appInstance->setStatus(
            PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
            'Trial completed, initiating removal process'
        )->save();

        $this->appInstance->info('Trial complete stage removal initiated', [
            'app_instance_id' => $this->appInstance->id,
            'previous_status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
            'new_status' => PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
        ]);

        Log::info('Trial complete stage removal initiated', [
            'app_instance_id' => $this->appInstance->id,
        ]);

        $this->polydockJobDone();
    }
} 