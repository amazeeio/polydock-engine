<?php

namespace App\Listeners;

use App\Events\PolydockAppInstanceStatusChanged;
use App\Jobs\ProcessPolydockAppInstanceJob;
use Illuminate\Support\Facades\Log;

class ProcessPolydockAppInstanceStatusChange
{
    /**
     * Handle the event.
     */
    public function handle(PolydockAppInstanceStatusChanged $event): void
    {
        Log::info('Dispatching ProcessPolydockAppInstanceJob', [
            'app_instance_id' => $event->appInstance->id,
            'store_app_id' => $event->appInstance->polydock_store_app_id,
            'store_app_name' => $event->appInstance->storeApp->name,
            'status' => $event->appInstance->status->value,
        ]);

        ProcessPolydockAppInstanceJob::dispatch($event->appInstance->id)
            ->onQueue('polydock-app-instance-processing');
    }
} 