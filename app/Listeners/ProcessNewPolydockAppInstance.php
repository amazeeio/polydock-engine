<?php

namespace App\Listeners;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use Illuminate\Support\Facades\Log;

class ProcessNewPolydockAppInstance
{
    /**
     * Handle the event.
     */
    public function handle(PolydockAppInstanceCreatedWithNewStatus $event): void
    {
        Log::info('New PolydockAppInstance created', [
            'app_instance_id' => $event->appInstance->id,
            'store_app_id' => $event->appInstance->polydock_store_app_id,
            'store_app_name' => $event->appInstance->storeApp->name,
            'status' => $event->appInstance->status->value,
        ]);
    }
} 