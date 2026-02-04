<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PolydockStoreWebhookCall>
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
            'id' => fake()->text(),
            'polydock_store_webhook_id' => fake()->text(),
            'event' => fake()->text(),
            'payload' => $this->faker->json(),
            'status' => fake()->text(),
            'attempt' => fake()->text(),
            'processed_at' => $this->faker->dateTime(),
            'response_code' => fake()->text(),
            'response_body' => $this->faker->paragraph(),
            'exception' => $this->faker->paragraph(),
            'created_at' => $this->faker->dateTime(),
            'updated_at' => $this->faker->dateTime(),
        ];
    }
}
