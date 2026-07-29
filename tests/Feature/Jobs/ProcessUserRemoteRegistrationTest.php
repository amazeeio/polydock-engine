<?php

namespace Tests\Feature\Jobs;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Events\UserRemoteRegistrationStatusChanged;
use App\Jobs\ProcessUserRemoteRegistration;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserRemoteRegistration;
use App\Polydock\Apps\Generic\PolydockApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessUserRemoteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected PolydockStoreApp $storeApp;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

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
    }

    private function makeTrialRegistration(): UserRemoteRegistration
    {
        return UserRemoteRegistration::create([
            'email' => 'trial-user@example.com',
            'status' => UserRemoteRegistrationStatusEnum::PENDING,
            'request_data' => [
                'register_type' => 'REQUEST_TRIAL',
                'email' => 'trial-user@example.com',
                'first_name' => 'Trial',
                'last_name' => 'User',
                'trial_app' => $this->storeApp->uuid,
                'aup_and_privacy_acceptance' => 1,
                'opt_in_to_product_updates' => 1,
            ],
        ]);
    }

    #[Test]
    public function it_never_persists_success_while_a_trial_is_still_being_created(): void
    {
        // The hosted form polls /api/register/{uuid} and treats a persisted
        // "success" without result_data.app_url as a hard failure, so the job
        // must never save SUCCESS before the instance is claimed.
        $registration = $this->makeTrialRegistration();

        $persistedStatuses = [];
        Event::listen(UserRemoteRegistrationStatusChanged::class, function ($event) use (&$persistedStatuses): void {
            $persistedStatuses[] = $event->registration->status;
        });

        (new ProcessUserRemoteRegistration($registration))->handle();

        $this->assertNotContains(UserRemoteRegistrationStatusEnum::SUCCESS, $persistedStatuses);

        $registration->refresh();
        $this->assertEquals(UserRemoteRegistrationStatusEnum::PROCESSING, $registration->status);
        $this->assertNotNull($registration->polydock_app_instance_id);
    }

    #[Test]
    public function it_still_ends_capture_only_registrations_as_success(): void
    {
        config()->set('polydock.register_only_captures', true);

        $registration = $this->makeTrialRegistration();

        (new ProcessUserRemoteRegistration($registration))->handle();

        $registration->refresh();
        $this->assertEquals(UserRemoteRegistrationStatusEnum::SUCCESS, $registration->status);
        $this->assertEquals('trial_registered', $registration->getResultValue('result_type'));
    }
}
