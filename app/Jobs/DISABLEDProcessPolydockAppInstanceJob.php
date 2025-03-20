<?php

namespace App\Jobs;

use App\Models\PolydockAppInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\PolydockEngine\Engine;
use App\PolydockEngine\PolydockLogger;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class DISABLEDProcessPolydockAppInstanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const UPGRADE_RUNNING_POLLING_INTERVAL = 15;
    private const DEPLOY_RUNNING_POLLING_INTERVAL = 15;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 3600; // Lock for 1 hour

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
            } else if(in_array($appInstance->status, PolydockAppInstance::$pendingStatuses)) {
                Log::info('PolydockAppInstance is in status ' . $appInstance->status->value . ' - processing pending status');
                $polydockEngine = new Engine(new PolydockLogger());
                $polydockEngine->processPolydockAppInstance($appInstance);
            } else if(in_array($appInstance->status, PolydockAppInstance::$completedStatuses)) {
                Log::info('PolydockAppInstance is in status ' . $appInstance->status->value . ' - processing completed status');
                $this->processCompletedPolydockAppInstance($appInstance);
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

    public function processCompletedPolydockAppInstance(PolydockAppInstance $appInstance)
    {
        if (!in_array($appInstance->status, PolydockAppInstance::$completedStatuses)) {
            throw new PolydockAppInstanceStatusFlowException(
                'Completed PolydockAppInstance must be in status ' . implode(', ', PolydockAppInstance::$completedStatuses),
                $appInstance->status
            );
        }

        switch($appInstance->status) {
            case PolydockAppInstanceStatus::PRE_CREATE_COMPLETED:
                $appInstance->setStatus(PolydockAppInstanceStatus::PENDING_CREATE)
                    ->save();
                break;
            case PolydockAppInstanceStatus::CREATE_COMPLETED:
                $appInstance->setStatus(PolydockAppInstanceStatus::PENDING_POST_CREATE)
                    ->save();
                break;
            case PolydockAppInstanceStatus::POST_CREATE_COMPLETED:
                $appInstance->setStatus(PolydockAppInstanceStatus::PENDING_PRE_DEPLOY)
                    ->save();
                break;
            case PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED:
                $appInstance->setStatus(PolydockAppInstanceStatus::PENDING_DEPLOY)
                    ->save();
                break;
            case PolydockAppInstanceStatus::DEPLOY_COMPLETED:
                $appInstance->setStatus(PolydockAppInstanceStatus::PENDING_POST_DEPLOY)
                    ->save();
                break;
            case PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED:
                // TODO: Implement logic to call poll for health status
                break;
            case PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED:
                $appInstance->setStatus(PolydockAppInstanceStatus::PENDING_REMOVE)
                    ->save();
                break;
            case PolydockAppInstanceStatus::REMOVE_COMPLETED:
                $appInstance->setStatus(PolydockAppInstanceStatus::PENDING_POST_REMOVE)
                    ->save();
                break;
            case PolydockAppInstanceStatus::POST_REMOVE_COMPLETED:
                $appInstance->setStatus(PolydockAppInstanceStatus::REMOVED)
                    ->save();
                break;
            case PolydockAppInstanceStatus::PRE_UPGRADE_COMPLETED:
                $appInstance->setStatus(PolydockAppInstanceStatus::PENDING_UPGRADE)
                    ->save();
                break;
            case PolydockAppInstanceStatus::UPGRADE_COMPLETED:
                $appInstance->setStatus(PolydockAppInstanceStatus::PENDING_POST_UPGRADE)
                    ->save();
                break;
            case PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED:
                 // TODO: Implement logic to call poll for health status
                break;
            default:
                throw new PolydockAppInstanceStatusFlowException(
                    'Completed PolydockAppInstance must be in status ' . implode(', ', PolydockAppInstance::$completedStatuses),
                    $appInstance->status
                );
        }
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware()
    {
        $appInstance = PolydockAppInstance::find($this->appInstanceId)->refresh();
        $uniqueId = "app-instance-{$appInstance->id}-status-{$appInstance->status->value}";

        Log::info('Unique ID for job: ' . $uniqueId);

        return [
            (new WithoutOverlapping($uniqueId))
                ->expireAfter(5)  // 5 seconds
                ->shared() // Use shared lock across different queues
                ->dontRelease()
        ];
    }
} 