<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstancesApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_register_status_endpoint_stays_public(): void
    {
        $uuid = $this->seedRegistration();

        $this->getJson("/api/register/{$uuid}")
            ->assertOk()
            ->assertJsonPath('status', UserRemoteRegistrationStatusEnum::PENDING->value);
    }

    public function test_legacy_register_create_endpoint_stays_public(): void
    {
        $this->postJson('/api/register', [
            'email' => 'legacy-create@example.com',
            'first_name' => 'Legacy',
            'last_name' => 'Flow',
            'register_type' => 'REQUEST_TRIAL',
            'trial_app' => 'app-uuid',
            'aup_and_privacy_acceptance' => 1,
            'opt_in_to_product_updates' => true,
        ])
            ->assertStatus(202)
            ->assertJsonPath('status', UserRemoteRegistrationStatusEnum::PENDING->value);
    }

    public function test_v1_instances_status_requires_token(): void
    {
        $uuid = $this->seedRegistration();

        $this->getJson("/api/v1/instances/{$uuid}")
            ->assertUnauthorized();
    }

    public function test_v1_instances_status_rejects_token_without_instances_read(): void
    {
        $uuid = $this->seedRegistration();
        $user = User::factory()->create();
        $token = $user->createToken('no-read', ['instances.write']);

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson("/api/v1/instances/{$uuid}")
            ->assertForbidden();
    }

    public function test_v1_instances_status_accepts_instances_read_token(): void
    {
        $uuid = $this->seedRegistration();
        $user = User::factory()->create();
        $token = $user->createToken('instances-read', ['instances.read']);

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->getJson("/api/v1/instances/{$uuid}")
            ->assertOk()
            ->assertJsonPath('status', UserRemoteRegistrationStatusEnum::PENDING->value)
            ->assertJsonPath('email', 'instance-test@example.com');
    }

    private function seedRegistration(): string
    {
        $uuid = (string) Str::uuid();

        DB::table('user_remote_registrations')->insert([
            'uuid' => $uuid,
            'email' => 'instance-test@example.com',
            'request_data' => json_encode(['register_type' => 'REQUEST_TRIAL']),
            'result_data' => null,
            'status' => UserRemoteRegistrationStatusEnum::PENDING->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $uuid;
    }
}
