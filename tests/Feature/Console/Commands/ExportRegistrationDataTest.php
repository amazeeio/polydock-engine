<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Models\UserRemoteRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportRegistrationDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_exports_registrations_to_stdout_and_redacts_sensitive_data(): void
    {
        UserRemoteRegistration::create([
            'email' => 'export-test@example.com',
            'status' => UserRemoteRegistrationStatusEnum::SUCCESS,
            'request_data' => [
                'password' => 'super-secret',
            ],
        ]);

        $this->artisan('polydock:export-registration-data', ['--stdout' => true, '--format' => 'json'])
            ->expectsOutputToContain('export-test@example.com')
            ->doesntExpectOutputToContain('super-secret')
            ->assertSuccessful();
    }

    public function test_warns_when_no_registrations_match(): void
    {
        $this->artisan('polydock:export-registration-data', ['--stdout' => true, '--status' => 'nonexistent'])
            ->expectsOutput('No registration data found matching the criteria.')
            ->assertFailed();
    }
}
