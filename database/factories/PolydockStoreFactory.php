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
            'lagoon_deploy_region_id_ext' => (string) fake()->numberBetween(1, 5),
            'lagoon_deploy_project_prefix' => fake()->randomLetter() . fake()->randomLetter(),
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