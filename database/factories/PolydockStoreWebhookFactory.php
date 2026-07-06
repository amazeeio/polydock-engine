<?php

namespace Database\Factories;

use App\Models\PolydockStore;
use App\Models\PolydockStoreWebhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolydockStoreWebhook>
 */
class PolydockStoreWebhookFactory extends Factory
{
    public function definition(): array
    {
        return [
            'polydock_store_id' => PolydockStore::factory(),
            'url' => fake()->url(),
            'active' => fake()->boolean(80), // 80% chance of being active
        ];
    }

    public function active(): self
    {
        return $this->state(fn (array $attributes) => [
            'active' => true,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
