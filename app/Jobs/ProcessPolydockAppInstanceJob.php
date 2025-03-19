<?php

namespace App\Jobs;

use App\Models\PolydockAppInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\PolydockEngine\Engine;
use App\PolydockEngine\PolydockLogger;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;

class ProcessPolydockAppInstanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $pendingStatuses = [
        PolydockAppInstanceStatus::PENDING_PRE_CREATE,
        PolydockAppInstanceStatus::PENDING_CREATE,
        PolydockAppInstanceStatus::PENDING_POST_CREATE,
        PolydockAppInstanceStatus::PENDING_PRE_DEPLOY,
        PolydockAppInstanceStatus::PENDING_DEPLOY,
        PolydockAppInstanceStatus::PENDING_POST_DEPLOY,
        PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
        PolydockAppInstanceStatus::PENDING_REMOVE,
        PolydockAppInstanceStatus::PENDING_POST_REMOVE,
        PolydockAppInstanceStatus::PENDING_PRE_UPGRADE,
        PolydockAppInstanceStatus::PENDING_UPGRADE,
        PolydockAppInstanceStatus::PENDING_POST_UPGRADE,
    ];

    private array $completedStatuses = [
        PolydockAppInstanceStatus::PRE_CREATE_COMPLETED,
        PolydockAppInstanceStatus::CREATE_COMPLETED,
        PolydockAppInstanceStatus::POST_CREATE_COMPLETED,
        PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED,
        PolydockAppInstanceStatus::DEPLOY_COMPLETED,
        PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED,
        PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED,
        PolydockAppInstanceStatus::REMOVE_COMPLETED,
        PolydockAppInstanceStatus::POST_REMOVE_COMPLETED,
        PolydockAppInstanceStatus::PRE_UPGRADE_COMPLETED,
        PolydockAppInstanceStatus::UPGRADE_COMPLETED,
        PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED,
    ];

    private array $failedStatuses = [
        PolydockAppInstanceStatus::PRE_CREATE_FAILED,
        PolydockAppInstanceStatus::CREATE_FAILED,
        PolydockAppInstanceStatus::POST_CREATE_FAILED,
        PolydockAppInstanceStatus::PRE_DEPLOY_FAILED,
        PolydockAppInstanceStatus::DEPLOY_FAILED,
        PolydockAppInstanceStatus::POST_DEPLOY_FAILED,
        PolydockAppInstanceStatus::PRE_REMOVE_FAILED,
        PolydockAppInstanceStatus::REMOVE_FAILED,
        PolydockAppInstanceStatus::POST_REMOVE_FAILED,
        PolydockAppInstanceStatus::PRE_UPGRADE_FAILED,
        PolydockAppInstanceStatus::UPGRADE_FAILED,
        PolydockAppInstanceStatus::POST_UPGRADE_FAILED,
    ];

    private array $pollingStatuses = [
        PolydockAppInstanceStatus::DEPLOY_RUNNING,
        PolydockAppInstanceStatus::UPGRADE_RUNNING,
        PolydockAppInstanceStatus::RUNNING_HEALTHY,
        PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
        PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE,
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $appInstanceId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Refresh the model from the database
        $appInstance = PolydockAppInstance::find($this->appInstanceId);
        
        if (!$appInstance) {
            Log::error('Failed to process PolydockAppInstance - not found', [
                'app_instance_id' => $this->appInstanceId
            ]);
            return;
        }

        Log::info('Starting to process PolydockAppInstance', [
            'app_instance_id' => $appInstance->id,
            'store_app_id' => $appInstance->polydock_store_app_id,
            'store_app_name' => $appInstance->storeApp->name,
            'status' => $appInstance->status->value
        ]);        

        try {
            if ($appInstance->status === PolydockAppInstanceStatus::NEW) {
                Log::info('PolydockAppInstance is in status ' . $appInstance->status->value . ' - processing new instance');
                $this->processNewPolydockAppInstance($appInstance);
            } else if(in_array($appInstance->status, $this->pendingStatuses)) {
                Log::info('PolydockAppInstance is in status ' . $appInstance->status->value . ' - processing pending status');
                $polydockEngine = new Engine(new PolydockLogger());
                $polydockEngine->processPolydockAppInstance($appInstance);
            } else {
                Log::info('PolydockAppInstance is in status ' . $appInstance->status->value . ' - skipping processing');
            }
        } catch (\Exception $e) {
            Log::error('Error processing PolydockAppInstance', [
                'app_instance_id' => $appInstance->id,
                'error' => $e->getMessage()
            ]);
        }

        Log::info('Finished processing PolydockAppInstance', [
            'app_instance_id' => $appInstance->id,
            'store_app_id' => $appInstance->polydock_store_app_id,
            'store_app_name' => $appInstance->storeApp->name,
            'status' => $appInstance->status->value
        ]);
    }

    public function processNewPolydockAppInstance(PolydockAppInstance $appInstance)
    {
        if ($appInstance->status != PolydockAppInstanceStatus::NEW) {
            throw new PolydockAppInstanceStatusFlowException(
                'New PolydockAppInstance must be in status NEW',
                $appInstance->status
            );
        }

        $appInstance->setStatus(PolydockAppInstanceStatus::PENDING_PRE_CREATE)
            ->save();
    }
} 