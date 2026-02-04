<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PolydockStoreWebhook>
 */
class PolydockStoreWebhookFactory extends Factory
{
    public function definition(): array
    {
        return [
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
