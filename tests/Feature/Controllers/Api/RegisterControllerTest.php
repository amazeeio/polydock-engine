<?php

namespace Tests\Feature\Controllers\Api;

use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Models\UserRemoteRegistration;
use App\Support\SensitiveDataRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
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

    /** @test */
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
}
