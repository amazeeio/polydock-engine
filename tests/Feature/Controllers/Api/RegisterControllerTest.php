<?php

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Models\UserRemoteRegistration;
use App\Support\SensitiveDataRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Rate-limit counters live in the cache, which RefreshDatabase does not
        // reset. Flush it so throttle state can't leak between tests (and make
        // the throttle test order-independent).
        $this->app['cache']->flush();

        parent::tearDown();
    }

    #[Test]
    public function it_redacts_sensitive_data_when_processing_registration(): void
    {
        // GIVEN we spy on logs
        Log::spy();

        // WHEN we post a registration request with sensitive data
        $response = $this->postJson('/api/register', [
            'email' => 'test-user@example.com',
            'password' => 'supersecret123',
            'api_key' => 'secret-api-key',
        ]);

        $response->assertStatus(202);

        // THEN 'Processing register request' log must be redacted
        Log::shouldHaveReceived('info')
            ->with('Processing register request', \Mockery::on(function ($context) {
                return isset($context['request']['password'])
                    && $context['request']['password'] === SensitiveDataRedactor::REDACTED_VALUE
                    && isset($context['request']['api_key'])
                    && $context['request']['api_key'] === SensitiveDataRedactor::REDACTED_VALUE;
            }));

        // AND 'User remote registration created' log must be redacted
        Log::shouldHaveReceived('info')
            ->with('User remote registration created', \Mockery::on(function ($context) {
                $reqData = $context['registration']['request_data'] ?? [];

                return isset($reqData['password'])
                    && $reqData['password'] === SensitiveDataRedactor::REDACTED_VALUE
                    && isset($reqData['api_key'])
                    && $reqData['api_key'] === SensitiveDataRedactor::REDACTED_VALUE;
            }));
    }

    #[Test]
    public function it_redacts_sensitive_data_when_showing_registration_status(): void
    {
        // GIVEN a registration exists with sensitive data in result_data and request_data
        $registration = UserRemoteRegistration::create([
            'email' => 'test-user@example.com',
            'status' => UserRemoteRegistrationStatusEnum::SUCCESS,
            'request_data' => [
                'password' => 'plaintext-req-password',
            ],
            'result_data' => [
                'app_admin_password' => 'plaintext-app-password',
                'app_url' => 'https://example.com',
            ],
        ]);

        // AND we spy on logs
        Log::spy();

        // WHEN we retrieve the registration status
        $response = $this->getJson("/api/register/{$registration->uuid}");

        $response->assertStatus(200);

        // THEN 'Showing user remote registration' log must be redacted
        Log::shouldHaveReceived('info')
            ->with('Showing user remote registration', \Mockery::on(function ($context) {
                $registrationArray = $context['registration'] ?? [];
                $reqData = $registrationArray['request_data'] ?? [];
                $resData = $registrationArray['result_data'] ?? [];

                return isset($reqData['password'])
                    && $reqData['password'] === SensitiveDataRedactor::REDACTED_VALUE
                    && isset($resData['app_admin_password'])
                    && $resData['app_admin_password'] === SensitiveDataRedactor::REDACTED_VALUE;
            }));
    }

    #[Test]
    public function it_throttles_repeated_registration_attempts(): void
    {
        $payload = [
            'email' => 'throttle-user@example.com',
            'password' => 'supersecret123',
            'api_key' => 'secret-api-key',
        ];

        // WHEN we post the registration endpoint up to its per-minute limit (10)
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/register', $payload);
        }

        // THEN the 11th request within the same minute is rejected with 429
        $this->postJson('/api/register', $payload)
            ->assertStatus(429);
    }

    #[Test]
    public function it_allows_trusted_ips_to_bypass_registration_throttle(): void
    {
        // GIVEN a trusted IP is configured
        config(['polydock.trusted_ips' => ['10.0.0.5']]);

        $payload = [
            'email' => 'trusted-throttle-user@example.com',
            'password' => 'supersecret123',
            'api_key' => 'secret-api-key',
        ];

        // WHEN we post 15 times from the trusted IP
        for ($i = 0; $i < 15; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
                ->postJson('/api/register', $payload)
                ->assertStatus(202);
        }
    }

    #[Test]
    public function it_throttles_registration_status_requests_per_ip_across_uuids(): void
    {
        // GIVEN two registrations exist
        $registrationA = UserRemoteRegistration::create([
            'email' => 'user-a@example.com',
            'status' => UserRemoteRegistrationStatusEnum::PENDING,
            'request_data' => [],
        ]);
        $registrationB = UserRemoteRegistration::create([
            'email' => 'user-b@example.com',
            'status' => UserRemoteRegistrationStatusEnum::PENDING,
            'request_data' => [],
        ]);

        // WHEN we exhaust the per-minute limit (60) against registration A
        for ($i = 0; $i < 60; $i++) {
            $this->getJson("/api/register/{$registrationA->uuid}")
                ->assertStatus(200);
        }

        // THEN a further request for registration A is throttled (429)
        $this->getJson("/api/register/{$registrationA->uuid}")
            ->assertStatus(429);

        // AND switching to registration B from the same IP is ALSO throttled —
        // the limit keys on IP so an attacker can't reset it by enumerating UUIDs
        $this->getJson("/api/register/{$registrationB->uuid}")
            ->assertStatus(429);
    }

    #[Test]
    public function it_allows_trusted_ips_to_bypass_registration_status_throttle(): void
    {
        // GIVEN a trusted IP is configured
        config(['polydock.trusted_ips' => ['10.0.0.5']]);

        // AND a registration exists
        $registration = UserRemoteRegistration::create([
            'email' => 'user-trusted@example.com',
            'status' => UserRemoteRegistrationStatusEnum::PENDING,
            'request_data' => [],
        ]);

        // WHEN we request registration 70 times from the trusted IP
        for ($i = 0; $i < 70; $i++) {
            $this->getJson("/api/register/{$registration->uuid}", ['REMOTE_ADDR' => '10.0.0.5'])
                ->assertStatus(200);
        }
    }
}
