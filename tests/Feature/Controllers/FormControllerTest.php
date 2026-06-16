<?php

namespace Tests\Feature\Controllers;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Jobs\ProcessUserRemoteRegistration;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserRemoteRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FormControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Prevent external reCAPTCHA API hits by default in tests
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => function ($request) {
                $responseToken = $request['response'] ?? '';
                if ($responseToken === 'invalid-token') {
                    return Http::response(['success' => false]);
                }

                return Http::response(['success' => true]);
            },
        ]);
    }

    /** @test */
    public function it_aborts_with_404_for_unknown_form_slugs()
    {
        $response = $this->get('/f/unknown-form-slug');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_renders_the_hosted_form_correctly_with_security_headers()
    {
        // Create sample store and app
        $store = PolydockStore::create([
            'name' => 'Europe Store',
            'status' => PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
            'lagoon_deploy_region_id_ext' => '1',
            'lagoon_deploy_project_prefix' => 'ft-eu',
            'lagoon_deploy_organization_id_ext' => '123',
        ]);

        $app = PolydockStoreApp::create([
            'polydock_store_id' => $store->id,
            'name' => 'CKEditor Demo',
            'polydock_app_class' => 'App\\PolydockApp',
            'lagoon_deploy_git' => 'git@github.com:example/app.git',
            'lagoon_deploy_branch' => 'main',
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'support_email' => 'support@example.com',
            'author' => 'Test Author',
            'description' => 'Test Description',
        ]);

        $response = $this->get('/f/drupal-ai-demo');

        $response->assertStatus(200);
        $response->assertViewIs('forms.drupal-ai-demo');
        $response->assertViewHas('form');
        $response->assertViewHas('regions');

        // Check framing security headers are set properly
        $response->assertHeaderMissing('X-Frame-Options');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self' amazee.ai www.amazee.ai localhost");
    }

    /** @test */
    public function it_fails_submitting_form_with_missing_fields()
    {
        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => '',
            'last_name' => 'Doe',
            'email' => 'invalid-email',
            'trial_app' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'status',
            'message',
            'errors',
        ]);
    }

    /** @test */
    public function it_fails_if_recaptcha_verification_fails()
    {
        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'trial_app' => '3aba2790-b1e9-47d4-b86d-06ffa4790895',
            'recaptcha' => 'invalid-token',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'reCAPTCHA verification failed. Please try again.',
        ]);
    }

    /** @test */
    public function it_successfully_submits_and_registers_user_trial()
    {
        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'organization' => 'Acme Corp',
            'job_title' => 'Web Developer',
            'country' => 'United States',
            'stage_in_ai_adoption' => 'just-curious',
            'interest_in_drupal_ai' => 'General testing',
            'trial_app' => '3aba2790-b1e9-47d4-b86d-06ffa4790895',
            'recaptcha' => 'valid-mock-token',
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'status' => 'pending',
            'message' => 'Registration pending',
        ]);
        $response->assertJsonStructure(['id']);

        // Assert UserRemoteRegistration model was created
        $this->assertDatabaseHas('user_remote_registrations', [
            'email' => 'john.doe@example.com',
            'status' => UserRemoteRegistrationStatusEnum::PENDING->value,
        ]);

        $registration = UserRemoteRegistration::first();
        $this->assertNotNull($registration->uuid);

        // Verify request payload mappings
        $this->assertEquals('John', $registration->getRequestValue('first_name'));
        $this->assertEquals('Doe', $registration->getRequestValue('last_name'));
        $this->assertEquals('Acme Corp', $registration->getRequestValue('company_name'));
        $this->assertEquals('just-curious', $registration->getRequestValue('instance_config_stage_in_ai_adoption'));
        $this->assertEquals('3aba2790-b1e9-47d4-b86d-06ffa4790895', $registration->getRequestValue('trial_app'));

        // Verify registration background job was pushed
        Queue::assertPushed(ProcessUserRemoteRegistration::class);
    }
}
