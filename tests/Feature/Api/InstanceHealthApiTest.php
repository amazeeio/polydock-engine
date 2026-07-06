<?php

namespace Tests\Feature\Api;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Apps\Generic\PolydockAiApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstanceHealthApiTest extends TestCase
{
    use RefreshDatabase;

    private PolydockAppInstance $instance;

    private string $uuid;

    protected function setUp(): void
    {
        parent::setUp();

        $store = PolydockStore::factory()->create();

        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);

        $group = UserGroup::factory()->create();

        $this->instance = new PolydockAppInstance;
        $this->instance->polydock_store_app_id = $storeApp->id;
        $this->instance->user_group_id = $group->id;
        $this->instance->name = 'test-instance';
        $this->instance->uuid = (string) Str::uuid();
        $this->instance->app_type = PolydockAiApp::class;
        $this->instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $this->instance->saveQuietly();

        $this->uuid = $this->instance->uuid;
    }

    public function test_health_check_without_configured_token_allows_request(): void
    {
        // GIVEN no token is configured
        Config::set('polydock.health_token', null);

        // WHEN we hit the endpoint without a token
        $response = $this->getJson("/api/instance/{$this->uuid}/health/running-healthy-claimed");

        // THEN it should be successful and update the status
        $response->assertStatus(200);
        $this->instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $this->instance->status);
    }

    public function test_health_check_with_configured_token_and_correct_token_allows_request(): void
    {
        // GIVEN a token is configured
        Config::set('polydock.health_token', 'secure-test-token');

        // WHEN we hit the endpoint with the correct token
        $response = $this->getJson("/api/instance/{$this->uuid}/health/running-healthy-claimed?token=secure-test-token");

        // THEN it should be successful
        $response->assertStatus(200);
        $this->instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $this->instance->status);
    }

    public function test_health_check_with_configured_token_and_missing_token_returns_401(): void
    {
        // GIVEN a token is configured
        Config::set('polydock.health_token', 'secure-test-token');

        // WHEN we hit the endpoint without a token
        $response = $this->getJson("/api/instance/{$this->uuid}/health/running-healthy-claimed");

        // THEN it should return 401 Unauthorized
        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Unauthorized: Invalid or missing health token',
            'status_code' => 401,
        ]);
    }

    public function test_health_check_with_configured_token_and_invalid_token_returns_401(): void
    {
        // GIVEN a token is configured
        Config::set('polydock.health_token', 'secure-test-token');

        // WHEN we hit the endpoint with an invalid token
        $response = $this->getJson("/api/instance/{$this->uuid}/health/running-healthy-claimed?token=wrong-token");

        // THEN it should return 401 Unauthorized
        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Unauthorized: Invalid or missing health token',
            'status_code' => 401,
        ]);
    }

    public function test_health_check_does_not_log_plaintext_token_on_error(): void
    {
        // GIVEN a token is configured
        Config::set('polydock.health_token', 'secure-test-token');

        // AND we expect Log::error to be called with redacted token
        Log::shouldReceive('error')
            ->once()
            ->with('Invalid status value', \Mockery::on(function ($context) {
                return isset($context['query']['token']) && $context['query']['token'] === '[REDACTED]';
            }));

        // WHEN we hit the endpoint with an invalid status and correct token
        $response = $this->getJson("/api/instance/{$this->uuid}/health/invalid-status?token=secure-test-token");

        // THEN it should return 400
        $response->assertStatus(400);
    }

    public function test_health_check_from_trusted_ip_bypasses_rate_limit(): void
    {
        // GIVEN a trusted IP is configured
        Config::set('polydock.trusted_ips', ['10.0.0.5']);
        Config::set('polydock.health_token', null);

        // WHEN we hit the endpoint 130 times from trusted IP
        for ($i = 0; $i < 130; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
                ->getJson("/api/instance/{$this->uuid}/health/running-healthy-claimed")
                ->assertStatus(200);
        }
    }

    public function test_health_check_with_valid_token_bypasses_rate_limit(): void
    {
        // GIVEN a token is configured
        Config::set('polydock.health_token', 'secure-test-token');

        // WHEN we hit the endpoint 130 times with the correct token
        for ($i = 0; $i < 130; $i++) {
            $this->getJson("/api/instance/{$this->uuid}/health/running-healthy-claimed?token=secure-test-token")
                ->assertStatus(200);
        }
    }

    public function test_health_check_throttles_per_ip_across_uuids(): void
    {
        Config::set('polydock.health_token', null); // No token gating

        // GIVEN another instance exists
        $storeApp = PolydockStoreApp::first();
        $group = UserGroup::first();

        $anotherInstance = new PolydockAppInstance;
        $anotherInstance->polydock_store_app_id = $storeApp->id;
        $anotherInstance->user_group_id = $group->id;
        $anotherInstance->name = 'another-instance';
        $anotherInstance->uuid = (string) Str::uuid();
        $anotherInstance->app_type = PolydockAiApp::class;
        $anotherInstance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $anotherInstance->saveQuietly();

        // WHEN we exhaust the per-minute limit (120) against the first UUID
        for ($i = 0; $i < 120; $i++) {
            $this->getJson("/api/instance/{$this->uuid}/health/running-healthy-claimed")
                ->assertStatus(200);
        }

        // THEN a further request for the first UUID is throttled
        $this->getJson("/api/instance/{$this->uuid}/health/running-healthy-claimed")
            ->assertStatus(429);

        // AND switching to the second UUID from the same IP is ALSO throttled —
        // the limit keys on IP so enumerating UUIDs can't reset the budget
        $this->getJson("/api/instance/{$anotherInstance->uuid}/health/running-healthy-claimed")
            ->assertStatus(429);
    }

    protected function tearDown(): void
    {
        // Flush cache to reset rate limiters
        $this->app['cache']->flush();

        parent::tearDown();
    }
}
