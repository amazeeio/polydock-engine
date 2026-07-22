<?php

namespace Tests\Feature\Controllers;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Forms\DrupalAIDemoDrupalOrgForm;
use App\Jobs\ProcessUserRemoteRegistration;
use App\Models\PolydockHostedForm;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserRemoteRegistration;
use App\Polydock\Apps\Generic\PolydockApp;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormControllerTest extends TestCase
{
    use RefreshDatabase;

    protected PolydockStoreApp $storeApp;

    protected PolydockHostedForm $hostedForm;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // reCAPTCHA is auto-disabled on explicit non-production Lagoon
        // environments; pin production so the tests exercise it regardless
        // of the local .env.
        config(['services.recaptcha.lagoon_environment_type' => 'production']);

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

        // Create sample public store and available trial app in the database
        $store = PolydockStore::create([
            'name' => 'Europe Store',
            'status' => PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
            'lagoon_deploy_region_id_ext' => '1',
            'lagoon_deploy_project_prefix' => 'ft-eu',
            'lagoon_deploy_organization_id_ext' => '123',
        ]);

        $this->storeApp = PolydockStoreApp::create([
            'polydock_store_id' => $store->id,
            'name' => 'CKEditor Demo',
            'polydock_app_class' => PolydockApp::class,
            'lagoon_deploy_git' => 'git@github.com:example/app.git',
            'lagoon_deploy_branch' => 'main',
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'support_email' => 'support@example.com',
            'author' => 'Test Author',
            'description' => 'Test Description',
        ]);

        $this->hostedForm = PolydockHostedForm::create([
            'slug' => 'drupal-ai-demo',
            'form_class' => DrupalAIDemoDrupalOrgForm::class,
            'enabled' => true,
            'title' => 'Private Drupal AI Demo on drupal.org',
            'seo_title' => 'Drupal AI Demo on drupal.org by amazee.ai',
        ]);

        $this->hostedForm->storeApps()->attach($this->storeApp);
    }

    #[Test]
    public function it_aborts_with_404_for_unknown_form_slugs()
    {
        $response = $this->get('/f/unknown-form-slug');

        $response->assertStatus(404);
    }

    #[Test]
    public function it_aborts_with_404_for_disabled_forms()
    {
        $this->hostedForm->update(['enabled' => false]);

        $response = $this->get('/f/drupal-ai-demo');

        $response->assertStatus(404);
    }

    #[Test]
    public function it_renders_the_hosted_form_correctly_with_security_headers()
    {
        $response = $this->get('/f/drupal-ai-demo');

        $response->assertStatus(200);
        $response->assertViewIs('forms.drupal-ai-demo');
        $response->assertViewHas('form');
        $response->assertViewHas('regions');

        // Check framing security headers are set properly
        $response->assertHeaderMissing('X-Frame-Options');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://amazee.ai https://www.amazee.ai http://localhost http://localhost:* https://drupal.org https://www.drupal.org https://new.drupal.org");
    }

    #[Test]
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

    #[Test]
    public function it_fails_submitting_form_with_invalid_country()
    {
        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'country' => 'Invalidistan',
            'trial_app' => $this->storeApp->uuid,
            'recaptcha' => 'valid-mock-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'status',
            'message',
            'errors',
        ]);
        $this->assertStringContainsString('The selected country is invalid', $response->json('message'));
    }

    #[Test]
    public function it_fails_if_recaptcha_verification_fails()
    {
        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'trial_app' => $this->storeApp->uuid,
            'recaptcha' => 'invalid-token',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'reCAPTCHA verification failed. Please try again.',
        ]);
    }

    #[Test]
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
            'trial_app' => $this->storeApp->uuid,
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
        $this->assertEquals($this->storeApp->uuid, $registration->getRequestValue('trial_app'));

        // Verify registration background job was pushed
        Queue::assertPushed(ProcessUserRemoteRegistration::class);
    }

    #[Test]
    public function it_rejects_submitting_form_with_invalid_trial_app_uuid()
    {
        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'trial_app' => '00000000-0000-0000-0000-000000000000', // Valid UUID structure but non-existent app
            'recaptcha' => 'valid-mock-token',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('selected trial app is invalid', $response->json('message'));
    }

    #[Test]
    public function it_allows_recaptcha_bypass_on_testing_environment_during_network_failure()
    {
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => function () {
                throw new \Exception('Network connection timeout');
            },
        ]);

        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'organization' => 'Acme Corp',
            'job_title' => 'Web Developer',
            'country' => 'United States',
            'stage_in_ai_adoption' => 'just-curious',
            'interest_in_drupal_ai' => 'General testing',
            'trial_app' => $this->storeApp->uuid,
            'recaptcha' => 'valid-mock-token',
        ]);

        // In testing environment, network exception is gracefully bypassed and the form is submitted
        $response->assertStatus(202);
        $response->assertJson([
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function it_blocks_recaptcha_bypass_on_staging_environment_during_network_failure()
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app->detectEnvironment(fn () => 'staging');

        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => function () {
                throw new \Exception('Network connection timeout');
            },
        ]);

        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'organization' => 'Acme Corp',
            'job_title' => 'Web Developer',
            'country' => 'United States',
            'stage_in_ai_adoption' => 'just-curious',
            'interest_in_drupal_ai' => 'General testing',
            'trial_app' => $this->storeApp->uuid,
            'recaptcha' => 'valid-mock-token',
        ]);

        // In staging/production environments, a network exception blocks submission and returns 500
        $response->assertStatus(500);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Unable to verify reCAPTCHA. Please try again later.',
        ]);
    }

    #[Test]
    public function it_skips_recaptcha_entirely_on_non_production_lagoon_environments()
    {
        config(['services.recaptcha.lagoon_environment_type' => 'development']);

        // No recaptcha token at all — must still submit successfully on dev
        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'trial_app' => $this->storeApp->uuid,
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'status' => 'pending',
        ]);

        // And the rendered form must not load the recaptcha widget
        $this->get('/f/drupal-ai-demo')
            ->assertStatus(200)
            ->assertDontSee('g-recaptcha', false);
    }

    #[Test]
    public function it_allows_submitting_without_recaptcha_when_recaptcha_is_disabled()
    {
        config(['services.recaptcha.enabled' => false]);

        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'organization' => 'Acme Corp',
            'job_title' => 'Web Developer',
            'country' => 'United States',
            'stage_in_ai_adoption' => 'just-curious',
            'interest_in_drupal_ai' => 'General testing',
            'trial_app' => $this->storeApp->uuid,
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function it_rejects_submitting_form_with_an_app_not_in_the_forms_allowlist()
    {
        // Available, trial-enabled app in a public store — but not allowed for this form
        $otherApp = PolydockStoreApp::create([
            'polydock_store_id' => $this->storeApp->polydock_store_id,
            'name' => 'Internal Dependency Track',
            'polydock_app_class' => PolydockApp::class,
            'lagoon_deploy_git' => 'git@github.com:example/other-app.git',
            'lagoon_deploy_branch' => 'main',
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'support_email' => 'support@example.com',
            'author' => 'Test Author',
            'description' => 'Test Description',
        ]);

        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'trial_app' => $otherApp->uuid,
            'recaptcha' => 'valid-mock-token',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('selected trial app is invalid', $response->json('message'));
    }

    #[Test]
    public function it_does_not_expose_apps_outside_the_forms_allowlist_when_rendering()
    {
        PolydockStoreApp::create([
            'polydock_store_id' => $this->storeApp->polydock_store_id,
            'name' => 'Internal Dependency Track',
            'polydock_app_class' => PolydockApp::class,
            'lagoon_deploy_git' => 'git@github.com:example/other-app.git',
            'lagoon_deploy_branch' => 'main',
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'support_email' => 'support@example.com',
            'author' => 'Test Author',
            'description' => 'Test Description',
        ]);

        $response = $this->get('/f/drupal-ai-demo');

        $response->assertStatus(200);
        $response->assertSee('CKEditor Demo');
        $response->assertDontSee('Internal Dependency Track');
    }

    #[Test]
    public function it_hides_and_rejects_attached_apps_that_are_no_longer_available()
    {
        // Attached to the form, but the app itself was disabled afterwards
        $this->storeApp->update(['status' => PolydockStoreAppStatusEnum::UNAVAILABLE]);

        $this->get('/f/drupal-ai-demo')
            ->assertStatus(200)
            ->assertDontSee('CKEditor Demo');

        $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'trial_app' => $this->storeApp->uuid,
            'recaptcha' => 'valid-mock-token',
        ])->assertStatus(422);
    }

    #[Test]
    public function it_hides_and_rejects_attached_apps_that_are_no_longer_available_for_trials()
    {
        $this->storeApp->update(['available_for_trials' => false]);

        $this->get('/f/drupal-ai-demo')
            ->assertStatus(200)
            ->assertDontSee('CKEditor Demo');

        $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'trial_app' => $this->storeApp->uuid,
            'recaptcha' => 'valid-mock-token',
        ])->assertStatus(422);
    }

    #[Test]
    public function it_rejects_all_submissions_when_the_form_has_no_allowed_apps()
    {
        $this->hostedForm->storeApps()->detach();

        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'trial_app' => $this->storeApp->uuid,
            'recaptcha' => 'valid-mock-token',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('selected trial app is invalid', $response->json('message'));
    }

    #[Test]
    public function it_rejects_submitting_form_with_app_uuid_from_a_private_store()
    {
        $privateStore = PolydockStore::create([
            'name' => 'USA Private Store',
            'status' => PolydockStoreStatusEnum::PRIVATE,
            'listed_in_marketplace' => false,
            'lagoon_deploy_region_id_ext' => '2',
            'lagoon_deploy_project_prefix' => 'ft-us-private',
            'lagoon_deploy_organization_id_ext' => '456',
        ]);

        $privateApp = PolydockStoreApp::create([
            'polydock_store_id' => $privateStore->id,
            'name' => 'Internal Private Tool',
            'polydock_app_class' => PolydockApp::class,
            'lagoon_deploy_git' => 'git@github.com:example/private-app.git',
            'lagoon_deploy_branch' => 'main',
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'support_email' => 'private-support@example.com',
            'author' => 'Internal Devs',
            'description' => 'Private app description',
        ]);

        $response = $this->postJson('/f/drupal-ai-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'organization' => 'Acme Corp',
            'job_title' => 'Web Developer',
            'country' => 'United States',
            'stage_in_ai_adoption' => 'just-curious',
            'interest_in_drupal_ai' => 'General testing',
            'trial_app' => $privateApp->uuid,
            'recaptcha' => 'valid-mock-token',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('selected trial app is invalid', $response->json('message'));
    }
}
