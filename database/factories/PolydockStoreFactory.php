<?php

namespace Database\Factories;

use App\Enums\PolydockStoreStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PolydockStore>
 */
class PolydockStoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'status' => fake()->randomElement(PolydockStoreStatusEnum::cases()),
            'listed_in_marketplace' => fake()->boolean(),
        ];
    }

    public function public(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => PolydockStoreStatusEnum::PUBLIC,
        ]);
    }

    public function private(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => PolydockStoreStatusEnum::PRIVATE,
        ]);
    }
} 