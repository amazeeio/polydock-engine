<?php

namespace Database\Factories;

use App\Enums\PolydockStoreWebhookCallStatusEnum;
use App\Models\PolydockStoreWebhook;
use App\Models\PolydockStoreWebhookCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolydockStoreWebhookCall>
 */
class PolydockStoreWebhookCallFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'polydock_store_webhook_id' => PolydockStoreWebhook::factory(),
            'event' => fake()->slug(),
            'payload' => ['message' => fake()->sentence()],
            'status' => PolydockStoreWebhookCallStatusEnum::PENDING,
            'attempt' => 0,
            'processed_at' => null,
            'response_code' => null,
            'response_body' => null,
            'exception' => null,
        ];
    }
}
