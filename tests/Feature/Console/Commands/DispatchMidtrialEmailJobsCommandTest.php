<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Jobs\ProcessPolydockAppInstanceJobs\Trial\ProcessMidtrialEmailJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchMidtrialEmailJobsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    #[\Override]
    protected function tearDown(): void
    {
        PolydockAppInstance::flushEventListeners();
        parent::tearDown();
    }

    private function createStoreApp(bool $sendMidtrialEmail = true): PolydockStoreApp
    {
        $store = PolydockStore::factory()->create();

        return PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'send_midtrial_email' => $sendMidtrialEmail,
        ]);
    }

    private function createInstance(
        PolydockStoreApp $storeApp,
        bool $isTrial = true,
        ?Carbon $sendMidtrialEmailAt = null,
        bool $midtrialEmailSent = false,
    ): PolydockAppInstance {
        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-'.uniqid();
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'test-instance';
        $instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance->app_type = 'test_app_type';
        $instance->data = [];
        $instance->is_trial = $isTrial;
        $instance->send_midtrial_email_at = $sendMidtrialEmailAt;
        $instance->midtrial_email_sent = $midtrialEmailSent;
        $instance->saveQuietly();

        return $instance;
    }

    public function test_dispatches_job_for_eligible_instance(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance($storeApp, sendMidtrialEmailAt: now()->subHour());

        $this->artisan('polydock:dispatch-midtrial-emails')->assertExitCode(0);

        Queue::assertPushed(ProcessMidtrialEmailJob::class, 1);
    }

    public function test_does_not_dispatch_when_email_already_sent(): void
    {
        $storeApp = $this->createStoreApp();
        $this->createInstance($storeApp, sendMidtrialEmailAt: now()->subHour(), midtrialEmailSent: true);

        $this->artisan('polydock:dispatch-midtrial-emails')->assertExitCode(0);

        Queue::assertNotPushed(ProcessMidtrialEmailJob::class);
    }

    public function test_does_not_dispatch_when_send_time_in_future(): void
    {
        $storeApp = $this->createStoreApp();
        $this->createInstance($storeApp, sendMidtrialEmailAt: now()->addHour());

        $this->artisan('polydock:dispatch-midtrial-emails')->assertExitCode(0);

        Queue::assertNotPushed(ProcessMidtrialEmailJob::class);
    }

    public function test_does_not_dispatch_when_not_a_trial(): void
    {
        $storeApp = $this->createStoreApp();
        $this->createInstance($storeApp, isTrial: false, sendMidtrialEmailAt: now()->subHour());

        $this->artisan('polydock:dispatch-midtrial-emails')->assertExitCode(0);

        Queue::assertNotPushed(ProcessMidtrialEmailJob::class);
    }

    public function test_does_not_dispatch_when_send_time_is_null(): void
    {
        $storeApp = $this->createStoreApp();
        $this->createInstance($storeApp, sendMidtrialEmailAt: null);

        $this->artisan('polydock:dispatch-midtrial-emails')->assertExitCode(0);

        Queue::assertNotPushed(ProcessMidtrialEmailJob::class);
    }

    public function test_does_not_dispatch_when_store_app_flag_disabled(): void
    {
        $storeApp = $this->createStoreApp(sendMidtrialEmail: false);
        $this->createInstance($storeApp, sendMidtrialEmailAt: now()->subHour());

        $this->artisan('polydock:dispatch-midtrial-emails')->assertExitCode(0);

        Queue::assertNotPushed(ProcessMidtrialEmailJob::class);
    }

    public function test_respects_per_run_cap(): void
    {
        config()->set('polydock.max_per_run_dispatch_midtrial_emails', 2);

        $storeApp = $this->createStoreApp();
        for ($i = 0; $i < 4; $i++) {
            $this->createInstance($storeApp, sendMidtrialEmailAt: now()->subHour());
        }

        $this->artisan('polydock:dispatch-midtrial-emails')->assertExitCode(0);

        Queue::assertPushed(ProcessMidtrialEmailJob::class, 2);
    }
}
