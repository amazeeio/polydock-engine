<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Enums\PolydockDeploymentRunTriggerSourceEnum;
use App\Models\PolydockDeploymentRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PolydockDeploymentRun>
 */
class PolydockDeploymentRunFactory extends Factory
{
    protected $model = PolydockDeploymentRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'polydock_store_app_id' => null,
            'triggered_by_user_id' => null,
            'trigger_source' => PolydockDeploymentRunTriggerSourceEnum::SCHEDULED,
            'lagoon_bulk_id' => 'bulk-'.fake()->unique()->lexify('??????'),
            'status' => PolydockDeploymentRunStatusEnum::RUNNING,
            'total_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
            'completed_at' => null,
            'last_polled_at' => null,
            'poll_attempts' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => PolydockDeploymentRunStatusEnum::COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
