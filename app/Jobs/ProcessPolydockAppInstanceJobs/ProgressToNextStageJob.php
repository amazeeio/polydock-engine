<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs;

use App\Models\PolydockAppInstance;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProgressToNextStageJob extends BaseJob implements ShouldQueue
{
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->polydockJobStart();

        $appInstance = $this->appInstance;

        if (! in_array($appInstance->status, PolydockAppInstance::$completedStatuses)) {
            throw new PolydockAppInstanceStatusFlowException(
                'ProgressToNextStageJob can only be run on completed statuses, got '.$appInstance->status->value,
            );
        }

        // Completed status => next pending status. POST_DEPLOY branches on
        // whether a user is attached; POST_UPGRADE ends the flow (polling
        // takes over from there).
        $next = match ($appInstance->status) {
            PolydockAppInstanceStatus::PRE_CREATE_COMPLETED => PolydockAppInstanceStatus::PENDING_CREATE,
            PolydockAppInstanceStatus::CREATE_COMPLETED => PolydockAppInstanceStatus::PENDING_POST_CREATE,
            PolydockAppInstanceStatus::POST_CREATE_COMPLETED => PolydockAppInstanceStatus::PENDING_PRE_DEPLOY,
            PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED => PolydockAppInstanceStatus::PENDING_DEPLOY,
            PolydockAppInstanceStatus::DEPLOY_COMPLETED => PolydockAppInstanceStatus::PENDING_POST_DEPLOY,
            PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED => ($appInstance->remoteRegistration || $appInstance->getKeyValue('user-email'))
                ? PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM
                : PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED,
            PolydockAppInstanceStatus::POLYDOCK_CLAIM_COMPLETED => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
            PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED => PolydockAppInstanceStatus::PENDING_REMOVE,
            PolydockAppInstanceStatus::REMOVE_COMPLETED => PolydockAppInstanceStatus::PENDING_POST_REMOVE,
            PolydockAppInstanceStatus::POST_REMOVE_COMPLETED => PolydockAppInstanceStatus::REMOVED,
            PolydockAppInstanceStatus::PRE_UPGRADE_COMPLETED => PolydockAppInstanceStatus::PENDING_UPGRADE,
            PolydockAppInstanceStatus::UPGRADE_COMPLETED => PolydockAppInstanceStatus::PENDING_POST_UPGRADE,
            PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED => null,
            default => throw new PolydockAppInstanceStatusFlowException(
                'ProgressToNextStageJob can only be run on completed statuses, got '.$appInstance->status->value,
            ),
        };

        if ($next === null) {
            Log::info('NOT Progressing app instance '.$appInstance->id
                .' to next stage from '.$appInstance->status->name.'. Polling should start now.');
        } else {
            Log::info('Progressing app instance '.$appInstance->id
                .' to next stage from '.$appInstance->status->name.' to '.$next->name);
            $appInstance->setStatus($next)->save();
        }

        $this->polydockJobDone();
    }
}
