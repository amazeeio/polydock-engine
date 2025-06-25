<?php

namespace App\Jobs\ProcessPolydockAppInstanceJobs;

use App\Jobs\ProcessPolydockAppInstanceJobs\BaseJob;
use App\Models\PolydockAppInstance;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;
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
        if(!$appInstance) {
            throw new \Exception('Failed to process PolydockAppInstance in ' . class_basename(self::class) . ' - not found');
        }

        if(!in_array($appInstance->status, PolydockAppInstance::$completedStatuses)) {
            throw new PolydockAppInstanceStatusFlowException(
                'ProgressToNextStageJob can only be run on completed statuses',
                $appInstance->status
            );
        }

        switch($appInstance->status) {
            case PolydockAppInstanceStatus::PRE_CREATE_COMPLETED:
                Log::info('Progressing app instance ' . $appInstance->id . ' to next stage from PRE_CREATE_COMPLETED to PENDING_CREATE');
                $appInstance
                    ->setStatus(PolydockAppInstanceStatus::PENDING_CREATE)
                    ->save();
                break;
            case PolydockAppInstanceStatus::CREATE_COMPLETED:
                Log::info('Progressing app instance ' . $appInstance->id . ' to next stage from CREATE_COMPLETED to PENDING_POST_CREATE');
                $appInstance
                    ->setStatus(PolydockAppInstanceStatus::PENDING_POST_CREATE)
                    ->save();
                break;
            case PolydockAppInstanceStatus::POST_CREATE_COMPLETED:
                Log::info('Progressing app instance ' . $appInstance->id . ' to next stage from POST_CREATE_COMPLETED to PENDING_PRE_DEPLOY');
                $appInstance
                    ->setStatus(PolydockAppInstanceStatus::PENDING_PRE_DEPLOY)
                    ->save();
                break;
            case PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED:
                Log::info('Progressing app instance ' . $appInstance->id . ' to next stage from PRE_DEPLOY_COMPLETED to PENDING_DEPLOY');
                $appInstance
                    ->setStatus(PolydockAppInstanceStatus::PENDING_DEPLOY)
                    ->save();
                break;
            case PolydockAppInstanceStatus::DEPLOY_COMPLETED:
                Log::info('Progressing app instance ' . $appInstance->id . ' to next stage from DEPLOY_COMPLETED to PENDING_POST_DEPLOY');
                $appInstance
                    ->setStatus(PolydockAppInstanceStatus::PENDING_POST_DEPLOY)
                    ->save();
                break;
            case PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED:  
                if($appInstance->remoteRegistration) {
                    Log::info('Progressing app instance ' 
                        . $appInstance->id 
                        . ' to next stage from POST_DEPLOY_COMPLETED to PENDING_POLYDOCK_CLAIM');
                    $appInstance
                        ->setStatus(PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM)
                        ->save();
                } else {
                    Log::info('Progressing app instance ' 
                        . $appInstance->id 
                        . ' to next stage from POST_DEPLOY_COMPLETED to RUNNING_HEALTHY_UNCLAIMED');
                    $appInstance
                        ->setStatus(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED)
                        ->save();
                }
                break;
            case PolydockAppInstanceStatus::POLYDOCK_CLAIM_COMPLETED:
                Log::info('Progressing app instance '
                    . $appInstance->id . ' to next stage from POLYDOCK_CLAIM_COMPLETED to RUNNING_HEALTHY_CLAIMED');
                
                $appInstance
                    ->setStatus(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED)
                    ->save();
                
                break;
            case PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED:
                Log::info('Progressing app instance ' . $appInstance->id . ' to next stage from PRE_REMOVE_COMPLETED to PENDING_REMOVE');
                $appInstance
                    ->setStatus(PolydockAppInstanceStatus::PENDING_REMOVE)
                    ->save();
                break;
            case PolydockAppInstanceStatus::REMOVE_COMPLETED:
                Log::info('Progressing app instance ' . $appInstance->id . ' to next stage from REMOVE_COMPLETED to PENDING_POST_REMOVE');
                $appInstance
                    ->setStatus(PolydockAppInstanceStatus::PENDING_POST_REMOVE)
                    ->save();
                break;
            case PolydockAppInstanceStatus::POST_REMOVE_COMPLETED:
                Log::info('NOT Progressing app instance ' . $appInstance->id . ' to next stage from POST_REMOVE_COMPLETED. This is the end of the line.');
                $appInstance
                    ->setStatus(PolydockAppInstanceStatus::REMOVED)
                    ->save();
                break;
            case PolydockAppInstanceStatus::PRE_UPGRADE_COMPLETED:
                Log::info('Progressing app instance ' . $appInstance->id . ' to next stage from PRE_UPGRADE_COMPLETED to PENDING_UPGRADE');
                $appInstance
                    ->setStatus(PolydockAppInstanceStatus::PENDING_UPGRADE)
                    ->save();
                break;
            case PolydockAppInstanceStatus::UPGRADE_COMPLETED:
                Log::info('Progressing app instance ' . $appInstance->id . ' to next stage from UPGRADE_COMPLETED to PENDING_POST_UPGRADE');
                $appInstance
                    ->setStatus(PolydockAppInstanceStatus::PENDING_POST_UPGRADE)
                    ->save();
                break;
            case PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED:
                Log::info('NOT Progressing app instance ' . $appInstance->id . ' to next stage from POST_UPGRADE_COMPLETED. Polling should start now.');
                break;
            default:
                throw new PolydockAppInstanceStatusFlowException(
                    'ProgressToNextStageJob can only be run on completed statuses',
                    $appInstance->status
                );
        }

        $this->polydockJobDone();
    }
}
