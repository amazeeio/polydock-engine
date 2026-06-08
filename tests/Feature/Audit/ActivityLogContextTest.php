<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ActivityLogContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_logger_can_record_a_basic_activity(): void
    {
        activity()->log('Test activity');

        $this->assertDatabaseHas('activity_log', [
            'description' => 'Test activity',
            'log_name' => 'audit',
        ]);
    }

    public function test_activity_for_authenticated_user_records_causer(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // Simulate a request context so the service provider can attach info
        $this->getJson('/api/user');

        activity()
            ->causedBy($user)
            ->log('User action');

        $activity = Activity::where('description', 'User action')->first();

        $this->assertNotNull($activity);
        $this->assertEquals($user->id, $activity->causer_id);
        $this->assertEquals(User::class, $activity->causer_type);
    }

    public function test_activity_for_sanctum_request_records_token_id_and_name(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('moad-service-token', ['*']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/user-activity-test-endpoint');

        // Since we can't hit a real endpoint easily, simulate the token context
        // by acting as the user with the token
        Sanctum::actingAs($user, ['*']);

        // Force the token to be the current access token
        $user->withAccessToken($token->accessToken);

        activity()
            ->causedBy($user)
            ->log('Token-identified action');

        $activity = Activity::where('description', 'Token-identified action')->first();

        $this->assertNotNull($activity);
        $this->assertEquals($token->accessToken->id, $activity->properties['token_id']);
        $this->assertEquals('moad-service-token', $activity->properties['token_name']);
    }

    public function test_activity_for_service_account_records_is_service_account_flag(): void
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate('service-account', config('auth.defaults.guard'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole($role);

        Sanctum::actingAs($user, ['*']);

        activity()
            ->causedBy($user)
            ->log('Service account action');

        $activity = Activity::where('description', 'Service account action')->first();

        $this->assertNotNull($activity);
        $this->assertTrue($activity->properties['is_service_account']);
    }

    public function test_activity_for_regular_user_records_is_service_account_false(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        activity()
            ->causedBy($user)
            ->log('Regular user action');

        $activity = Activity::where('description', 'Regular user action')->first();

        $this->assertNotNull($activity);
        $this->assertFalse($activity->properties['is_service_account']);
    }

    public function test_activity_without_causer_records_is_service_account_false(): void
    {
        activity()->log('Anonymous action');

        $activity = Activity::where('description', 'Anonymous action')->first();

        $this->assertNotNull($activity);
        $this->assertFalse($activity->properties['is_service_account']);
    }

    public function test_activity_for_console_command_does_not_crash_without_request(): void
    {
        // In console context, request() returns a synthetic request.
        // The provider should handle this gracefully.
        activity()->log('Console action');

        $activity = Activity::where('description', 'Console action')->first();

        $this->assertNotNull($activity);
        // Should still have the is_service_account field
        $this->assertArrayHasKey('is_service_account', $activity->properties->toArray());
    }

    public function test_activity_properties_are_redacted_for_sensitive_keys(): void
    {
        activity()
            ->withProperties([
                'api_key' => 'sk-super-secret-key',
                'name' => 'safe-value',
                'database_password' => 'hunter2',
            ])
            ->log('Action with secrets');

        $activity = Activity::where('description', 'Action with secrets')->first();

        $this->assertNotNull($activity);
        $this->assertEquals('REDACTED', $activity->properties['api_key']);
        $this->assertEquals('safe-value', $activity->properties['name']);
        $this->assertEquals('REDACTED', $activity->properties['database_password']);
    }

    public function test_activity_records_ip_address(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        // Make a request to set the request context
        $this->getJson('/api/user');

        activity()
            ->causedBy($user)
            ->log('Request with IP');

        $activity = Activity::where('description', 'Request with IP')->first();

        $this->assertNotNull($activity);
        $this->assertArrayHasKey('ip', $activity->properties->toArray());
    }
}
