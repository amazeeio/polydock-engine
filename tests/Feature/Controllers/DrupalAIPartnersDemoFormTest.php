<?php

namespace Tests\Feature\Controllers;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Jobs\ProcessUserRemoteRegistration;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserRemoteRegistration;
use App\Polydock\Apps\Generic\PolydockApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DrupalAIPartnersDemoFormTest extends TestCase
{
    use RefreshDatabase;

    protected PolydockStoreApp $storeApp;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Prevent external reCAPTCHA API hits by default in tests
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

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
            'name' => 'Drupal AI Partners Demo',
            'polydock_app_class' => PolydockApp::class,
            'lagoon_deploy_git' => 'git@gitlab.com:drupal-infrastructure/ai/drupal-ai-starter-template.git',
            'lagoon_deploy_branch' => 'main',
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
            'available_for_trials' => true,
            'support_email' => 'support@example.com',
            'author' => 'Test Author',
            'description' => 'Test Description',
        ]);
    }

    #[Test]
    public function it_renders_the_partners_demo_form_with_security_headers()
    {
        $response = $this->get('/f/drupal-ai-partners-demo');

        $response->assertStatus(200);
        $response->assertViewIs('forms.drupal-ai-partners-demo');
        $response->assertViewHas('form');
        $response->assertViewHas('regions');
        $response->assertSee('Drupal AI Initiative - Partners Demo');
        $response->assertSee('drupal-ai-starter-template');
        $response->assertSee('Please keep this private and only for the members of the Drupal AI initiative.');

        $response->assertHeaderMissing('X-Frame-Options');
        $response->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://amazee.ai https://www.amazee.ai http://localhost http://localhost:*");
    }

    #[Test]
    public function it_fails_submitting_form_with_missing_fields()
    {
        $response = $this->postJson('/f/drupal-ai-partners-demo', [
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
    public function it_fails_submitting_form_without_accepting_terms()
    {
        $response = $this->postJson('/f/drupal-ai-partners-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'trial_app' => $this->storeApp->uuid,
            'recaptcha' => 'valid-mock-token',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('accept_terms', $response->json('errors'));
    }

    #[Test]
    public function it_successfully_submits_and_registers_user_trial()
    {
        $response = $this->postJson('/f/drupal-ai-partners-demo', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'accept_terms' => '1',
            'trial_app' => $this->storeApp->uuid,
            'recaptcha' => 'valid-mock-token',
        ]);

        $response->assertStatus(202);
        $response->assertJson([
            'status' => 'pending',
            'message' => 'Registration pending',
        ]);
        $response->assertJsonStructure(['id']);

        $this->assertDatabaseHas('user_remote_registrations', [
            'email' => 'john.doe@example.com',
            'status' => UserRemoteRegistrationStatusEnum::PENDING->value,
        ]);

        $registration = UserRemoteRegistration::first();
        $this->assertEquals('John', $registration->getRequestValue('first_name'));
        $this->assertEquals('Doe', $registration->getRequestValue('last_name'));
        $this->assertEquals($this->storeApp->uuid, $registration->getRequestValue('trial_app'));

        Queue::assertPushed(ProcessUserRemoteRegistration::class);
    }
}
