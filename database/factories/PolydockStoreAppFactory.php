<?php

namespace Database\Factories;

use App\Enums\PolydockStoreAppStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PolydockStoreApp>
 */
class PolydockStoreAppFactory extends Factory
{
    public function definition(): array
    {
        return [
            'polydock_app_class' => \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class,
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'author' => fake()->name(),
            'website' => fake()->url(),
            'support_email' => fake()->email(),
            'lagoon_deploy_git' => 'git@github.com:'.fake()->userName().'/'.fake()->slug().'.git',
            'lagoon_deploy_branch' => 'main',
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => fake()->boolean(),
        ];
    }

    public function unavailable(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => PolydockStoreAppStatusEnum::UNAVAILABLE,
        ]);
    }

    public function availableForTrials(): self
    {
        return $this->state(fn (array $attributes) => [
            'available_for_trials' => true,
        ]);
    }

    public function notAvailableForTrials(): self
    {
        return $this->state(fn (array $attributes) => [
            'available_for_trials' => false,
        ]);
    }
}
