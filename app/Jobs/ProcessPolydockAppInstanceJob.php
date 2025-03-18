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

        try {
            $polydockEngine = new Engine(new PolydockLogger());
            $polydockEngine->processPolydockAppInstance($appInstance);
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
} 