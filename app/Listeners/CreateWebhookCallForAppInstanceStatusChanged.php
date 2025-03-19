<?php

namespace App\Listeners;

use App\Events\PolydockAppInstanceStatusChanged;
use App\Models\PolydockStoreWebhookCall;
use Illuminate\Support\Facades\Log;

class CreateWebhookCallForAppInstanceStatusChanged
{
    /**
     * Handle the event.
     */
    public function handle(PolydockAppInstanceStatusChanged $event): void
    {
        $webhooks = $event->appInstance->storeApp->store->webhooks()
            ->where('active', true)
            ->get();

        if ($webhooks->isEmpty()) {
            Log::info('No active webhooks found for app instance status change', [
                'app_instance_id' => $event->appInstance->id,
                'store_id' => $event->appInstance->storeApp->store->id
            ]);
            return;
        }

        foreach ($webhooks as $webhook) {
            PolydockStoreWebhookCall::create([
                'polydock_store_webhook_id' => $webhook->id,
                'event' => $event->previousStatus === null ? 'app_instance.created' : 'app_instance.status_changed',
                'payload' => [
                    'app_instance_id' => $event->appInstance->id,
                    'store_id' => $event->appInstance->storeApp->store->id,
                    'store_name' => $event->appInstance->storeApp->store->name,
                    'store_app_id' => $event->appInstance->polydock_store_app_id,
                    'store_app_name' => $event->appInstance->storeApp->name,
                    'previous_status' => $event->previousStatus?->value,
                    'current_status' => $event->appInstance->status->value,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            Log::info(
                $event->previousStatus === null 
                    ? 'Created webhook call for new app instance' 
                    : 'Created webhook call for app instance status change',
                [
                    'webhook_id' => $webhook->id,
                    'app_instance_id' => $event->appInstance->id,
                    'previous_status' => $event->previousStatus?->value,
                    'current_status' => $event->appInstance->status->value
                ]
            );
        }
    }
} 