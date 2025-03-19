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

        $pendingStatuses = [
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

        try {
            if ($appInstance->status === PolydockAppInstanceStatus::NEW) {
                Log::info('PolydockAppInstance is in status ' . $appInstance->status->value . ' - processing new instance');
                $this->processNewPolydockAppInstance($appInstance);
            } else if(in_array($appInstance->status, $pendingStatuses)) {
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