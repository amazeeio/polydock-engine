<?php

namespace Tests\Feature;

use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Events\UserRemoteRegistrationCreated;
use App\Events\UserRemoteRegistrationStatusChanged;
use App\Jobs\ProcessPolydockAppInstanceJobs\Claim\ClaimJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\New\ProcessNewJob;
use App\Jobs\ProcessUserRemoteRegistration;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserRemoteRegistration;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegisterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected PolydockStore $store;
    protected PolydockStoreApp $storeApp;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test data
        $this->store = PolydockStore::factory()->public()->create();

        $this->storeApp = PolydockStoreApp::factory()->availableForTrials()->create([
            'polydock_store_id' => $this->store->id,
            'polydock_app_class' => 'Tests\\Doubles\\AlphaTestPolydockServiceProvider',
        ]);
    }

    public function test_complete_register_workflow_to_claim_script(): void
    {
        // Step 1: Create registration manually to avoid SQLite UUID timing issues
        $registrationData = [
            'register_type' => 'REQUEST_TRIAL',
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'trial_app' => $this->storeApp->id,
            'aup_and_privacy_acceptance' => true,
            'opt_in_to_product_updates' => false,
        ];

        // Fake events after defining data but before creating model
        Event::fake();
        Queue::fake();

        // Create the registration directly for testing
        $registration = UserRemoteRegistration::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'email' => 'test@example.com',
            'request_data' => $registrationData,
            'status' => UserRemoteRegistrationStatusEnum::PENDING,
        ]);

        $this->assertNotNull($registration->uuid);
        $this->assertEquals('test@example.com', $registration->email);
        $this->assertEquals(UserRemoteRegistrationStatusEnum::PENDING, $registration->status);
        $this->assertEquals($registrationData, $registration->request_data);

        // Verify UserRemoteRegistrationCreated event was fired
        Event::assertDispatched(UserRemoteRegistrationCreated::class, function ($event) use ($registration) {
            return $event->userRemoteRegistration->id === $registration->id;
        });

        // Step 2: Process the registration job
        $processJob = new ProcessUserRemoteRegistration($registration);
        $processJob->handle();

        // Refresh registration to see changes
        $registration->refresh();
        
        // Verify registration status changed to PROCESSING
        $this->assertEquals(UserRemoteRegistrationStatusEnum::PROCESSING, $registration->status);

        // Verify User and UserGroup were created
        $this->assertNotNull($registration->user_id);
        $this->assertNotNull($registration->user_group_id);

        $user = $registration->user;
        $userGroup = $registration->userGroup;
        
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('Test', $user->first_name);
        $this->assertEquals('User', $user->last_name);

        // Verify PolydockAppInstance was created/allocated
        $this->assertNotNull($registration->polydock_app_instance_id);
        $appInstance = $registration->appInstance;
        
        $this->assertNotNull($appInstance);
        $this->assertEquals($this->storeApp->id, $appInstance->polydock_store_app_id);
        $this->assertEquals($userGroup->id, $appInstance->user_group_id);
        $this->assertNotNull($appInstance->trial_expires_at);

        // Step 3: Verify app instance progresses through creation stages
        // Check initial status (should be NEW or PENDING_PRE_CREATE)
        $this->assertContains($appInstance->status, [
            PolydockAppInstanceStatus::NEW,
            PolydockAppInstanceStatus::PENDING_PRE_CREATE,
            PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM // If pre-allocated
        ]);

        // Simulate the workflow progression by manually updating status and dispatching jobs
        if ($appInstance->status === PolydockAppInstanceStatus::NEW) {
            // Trigger ProcessNewJob
            $processNewJob = new ProcessNewJob($appInstance);
            $processNewJob->handle();
            $appInstance->refresh();
        }

        // For this test, we'll simulate a pre-allocated instance that goes directly to claim
        // Update status to PENDING_POLYDOCK_CLAIM
        $appInstance->update(['status' => PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM]);

        // Verify PolydockAppInstanceStatusChanged event
        Event::assertDispatched(PolydockAppInstanceStatusChanged::class);

        // Step 4: Test the claim job execution
        $claimJob = new ClaimJob($appInstance);
        
        // Execute the claim job
        $claimJob->handle();
        
        // Refresh to see status changes
        $appInstance->refresh();
        
        // Verify claim script was executed (status should progress)
        $this->assertEquals(PolydockAppInstanceStatus::POLYDOCK_CLAIM_COMPLETED, $appInstance->status);

        // Step 5: Verify final registration status
        $registration->refresh();
        $this->assertEquals(UserRemoteRegistrationStatusEnum::SUCCESS, $registration->status);
        $this->assertNotNull($registration->result_data);

        // Verify trial URLs are set
        $this->assertNotNull($appInstance->app_instance_url);
        
        // Verify the workflow created all necessary relationships
        $this->assertTrue($user->groups->contains($userGroup));
        $this->assertTrue($userGroup->appInstances->contains($appInstance));
        $this->assertEquals($appInstance->id, $registration->polydock_app_instance_id);
    }

    public function test_register_workflow_with_validation_errors(): void
    {
        // Test missing required fields
        $incompleteData = [
            'email' => 'test@example.com',
            // Missing required fields
        ];

        $response = $this->postJson('/api/register', $incompleteData);
        
        // Should return validation errors
        $response->assertStatus(422);
    }

    public function test_register_workflow_with_invalid_trial_app(): void
    {
        Queue::fake();

        $registrationData = [
            'register_type' => 'REQUEST_TRIAL',
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'trial_app' => 99999, // Non-existent app
            'aup_and_privacy_acceptance' => true,
            'opt_in_to_product_updates' => false,
        ];

        $response = $this->postJson('/api/register', $registrationData);
        $response->assertStatus(Response::HTTP_ACCEPTED);

        // Get the registration
        $responseData = $response->json();
        $registration = UserRemoteRegistration::where('uuid', $responseData['id'])->first();

        // Process the job - should fail
        $processJob = new ProcessUserRemoteRegistration($registration);
        $processJob->handle();

        // Verify registration failed
        $registration->refresh();
        $this->assertEquals(UserRemoteRegistrationStatusEnum::FAILED, $registration->status);
        $this->assertNotNull($registration->result_data);
    }

    public function test_get_registration_status(): void
    {
        // Create a registration
        $registration = UserRemoteRegistration::create([
            'email' => 'test@example.com',
            'request_data' => ['test' => 'data'],
            'status' => UserRemoteRegistrationStatusEnum::PROCESSING,
        ]);

        // Test GET endpoint
        $response = $this->getJson("/api/register/{$registration->uuid}");
        
        $response->assertStatus(Response::HTTP_OK);
        $responseData = $response->json();
        
        $this->assertEquals(UserRemoteRegistrationStatusEnum::PROCESSING->value, $responseData['status']);
        $this->assertEquals('test@example.com', $responseData['email']);
    }

    public function test_get_nonexistent_registration(): void
    {
        $response = $this->getJson('/api/register/invalid-uuid');
        
        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $responseData = $response->json();
        
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('Registration not found', $responseData['message']);
    }
}