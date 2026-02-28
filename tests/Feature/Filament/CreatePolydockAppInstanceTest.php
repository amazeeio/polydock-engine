<?php

namespace Tests\Feature\Filament;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages\CreatePolydockAppInstance;
use App\Jobs\ProcessUserRemoteRegistration;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Models\UserRemoteRegistration;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class CreatePolydockAppInstanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected PolydockStoreApp $storeApp;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an admin user
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);

        // Create a store and store app available for trials
        $store = PolydockStore::factory()->create();
        $this->storeApp = PolydockStoreApp::factory()
            ->availableForTrials()
            ->create([
                'polydock_store_id' => $store->id,
                'status' => PolydockStoreAppStatusEnum::AVAILABLE,
            ]);

        // Set the admin panel as current for Filament
        Filament::setCurrentPanel(
            Filament::getPanel('admin'),
        );
    }

    public function test_admin_can_create_app_instance(): void
    {
        Queue::fake();

        $this->actingAs($this->admin);

        Livewire::test(CreatePolydockAppInstance::class)
            ->fillForm([
                'email' => 'newuser@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'organization' => 'Test Org',
                'trial_app' => $this->storeApp->uuid,
                'is_trial' => true,
                'aup_and_privacy_acceptance' => true,
                'opt_in_to_product_updates' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Verify registration was created
        $this->assertDatabaseHas('user_remote_registrations', [
            'email' => 'newuser@example.com',
        ]);

        // Verify job was dispatched
        Queue::assertPushed(ProcessUserRemoteRegistration::class);
    }

    public function test_create_form_validates_required_fields(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreatePolydockAppInstance::class)
            ->fillForm([
                'email' => '',
                'first_name' => '',
                'last_name' => '',
                'trial_app' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'email' => 'required',
                'first_name' => 'required',
                'last_name' => 'required',
                'trial_app' => 'required',
            ]);
    }

    public function test_registration_includes_admin_created_flag(): void
    {
        Queue::fake();

        $this->actingAs($this->admin);

        $registration = UserRemoteRegistration::create([
            'email' => 'flagtest@example.com',
            'request_data' => [
                'email' => 'flagtest@example.com',
                'first_name' => 'Admin',
                'last_name' => 'Created',
                'register_type' => 'REQUEST_TRIAL',
                'trial_app' => $this->storeApp->uuid,
                'aup_and_privacy_acceptance' => 1,
                'opt_in_to_product_updates' => 1,
                'admin_created' => true,
                'is_trial' => true,
            ],
        ]);

        $this->assertNotNull($registration);
        $this->assertTrue($registration->request_data['admin_created']);
        $this->assertEquals('REQUEST_TRIAL', $registration->request_data['register_type']);
    }

    public function test_store_app_factory_creates_available_trial_app(): void
    {
        $this->assertTrue($this->storeApp->available_for_trials);
        $this->assertEquals(PolydockStoreAppStatusEnum::AVAILABLE, $this->storeApp->status);
        $this->assertNotNull($this->storeApp->uuid);
    }

    public function test_user_remote_registration_stores_request_data(): void
    {
        $registration = UserRemoteRegistration::create([
            'email' => 'datatest@example.com',
            'request_data' => [
                'email' => 'datatest@example.com',
                'first_name' => 'Data',
                'last_name' => 'Test',
                'register_type' => 'REQUEST_TRIAL',
                'trial_app' => $this->storeApp->uuid,
                'aup_and_privacy_acceptance' => 1,
                'custom_field' => 'custom_value',
            ],
        ]);

        $this->assertEquals('Data', $registration->getRequestValue('first_name'));
        $this->assertEquals('Test', $registration->getRequestValue('last_name'));
        $this->assertEquals('custom_value', $registration->getRequestValue('custom_field'));
    }

    public function test_process_user_remote_registration_job_can_be_dispatched(): void
    {
        Queue::fake();

        $registration = UserRemoteRegistration::create([
            'email' => 'jobtest@example.com',
            'request_data' => [
                'email' => 'jobtest@example.com',
                'first_name' => 'Job',
                'last_name' => 'Test',
                'register_type' => 'REQUEST_TRIAL',
                'trial_app' => $this->storeApp->uuid,
                'aup_and_privacy_acceptance' => 1,
                'opt_in_to_product_updates' => 1,
            ],
        ]);

        ProcessUserRemoteRegistration::dispatch($registration);

        Queue::assertPushed(ProcessUserRemoteRegistration::class, function ($job) use ($registration) {
            // Use reflection to access the private registration property
            $reflection = new \ReflectionClass($job);
            $property = $reflection->getProperty('registration');
            $jobRegistration = $property->getValue($job);

            return $jobRegistration->id === $registration->id && $jobRegistration->email === 'jobtest@example.com';
        });
    }
}
