<?php

namespace App\Listeners;

use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Events\UserRemoteRegistrationStatusChanged;
use App\Models\PolydockStoreWebhookCall;
use Illuminate\Support\Facades\Log;

class CreateWebhookCallForRegistrationStatusSuccessOrFailed
{
    /**
     * Handle the event.
     */
    public function handle(UserRemoteRegistrationStatusChanged $event): void
    {
        // Only create webhook calls for success or failed status
        if (! in_array($event->registration->status, [
            UserRemoteRegistrationStatusEnum::SUCCESS,
            UserRemoteRegistrationStatusEnum::FAILED,
        ])) {
            return;
        }

        // Check if store app exists before proceeding
        if (! $event->registration->storeApp || ! $event->registration->storeApp->store) {
            Log::info('No store app associated with registration, skipping webhook calls', [
                'registration_id' => $event->registration->id,
            ]);

            return;
        }

        Log::info('Creating webhook calls for registration status change', [
            'registration_id' => $event->registration->id,
            'previous_status' => $event->previousStatus,
            'new_status' => $event->registration->status->value,
        ]);

        // Create webhook calls for all active webhooks
        $event->registration->storeApp->store->webhooks()
            ->where('active', true)
            ->get()
            ->each(function ($webhook) use ($event) {
                try {
                    Log::info('Creating webhook call for registration status change', [
                        'webhook_id' => $webhook->id,
                        'event' => 'registration.status.changed',
                        'payload' => [
                            'registration_id' => $event->registration->id,
                            'previous_status' => $event->previousStatus,
                            'new_status' => $event->registration->status->value,
                        ],
                    ]);

                    PolydockStoreWebhookCall::create([
                        'polydock_store_webhook_id' => $webhook->id,
                        'event' => 'registration.status.changed',
                        'payload' => [
                            'registration_id' => $event->registration->id,
                            'previous_status' => $event->previousStatus,
                            'new_status' => $event->registration->status->value,
                            'user_id' => $event->registration->user_id,
                            'user' => $event->registration->user->toArray(),
                            'user_group_id' => $event->registration->user_group_id,
                            'user_group' => $event->registration->userGroup->toArray(),
                            'trial_app_id' => $event->registration->polydock_store_app_id,
                            'trial_app' => $event->registration->storeApp->toArray(),
                            'request_data' => $event->registration->request_data,
                            'result_data' => $event->registration->result_data,
                            'created_at' => $event->registration->created_at,
                            'updated_at' => $event->registration->updated_at,
                        ],
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to create webhook call for registration status change', [
                        'webhook_id' => $webhook->id,
                        'registration_id' => $event->registration->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }
}
