<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Jobs\ProcessPolydockAppInstanceJobs\Trial\ProcessTrialCompleteStageRemovalJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchTrialCompleteStageRemovalJobsCommandTest extends TestCase
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

    private function createStoreApp(): PolydockStoreApp
    {
        $store = PolydockStore::factory()->create();

        return PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
    }

    private function createInstance(
        PolydockStoreApp $storeApp,
        bool $isTrial = true,
        PolydockAppInstanceStatus $status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ?Carbon $trialEndsAt = null,
    ): PolydockAppInstance {
        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-'.uniqid();
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'test-instance';
        $instance->status = $status;
        $instance->app_type = 'test_app_type';
        $instance->data = [];
        $instance->is_trial = $isTrial;
        $instance->trial_ends_at = $trialEndsAt;
        $instance->saveQuietly();

        return $instance;
    }

    public function test_dispatches_job_for_eligible_instance(): void
    {
        $storeApp = $this->createStoreApp();
        $this->createInstance($storeApp, trialEndsAt: now()->subHour());

        $this->artisan('polydock:dispatch-trial-complete-stage-removal')->assertExitCode(0);

        Queue::assertPushed(ProcessTrialCompleteStageRemovalJob::class, 1);
    }

    public function test_does_not_dispatch_when_trial_ends_in_future(): void
    {
        $storeApp = $this->createStoreApp();
        $this->createInstance($storeApp, trialEndsAt: now()->addHour());

        $this->artisan('polydock:dispatch-trial-complete-stage-removal')->assertExitCode(0);

        Queue::assertNotPushed(ProcessTrialCompleteStageRemovalJob::class);
    }

    public function test_does_not_dispatch_when_not_a_trial(): void
    {
        $storeApp = $this->createStoreApp();
        $this->createInstance($storeApp, isTrial: false, trialEndsAt: now()->subHour());

        $this->artisan('polydock:dispatch-trial-complete-stage-removal')->assertExitCode(0);

        Queue::assertNotPushed(ProcessTrialCompleteStageRemovalJob::class);
    }

    public function test_does_not_dispatch_when_trial_ends_at_is_null(): void
    {
        $storeApp = $this->createStoreApp();
        $this->createInstance($storeApp, trialEndsAt: null);

        $this->artisan('polydock:dispatch-trial-complete-stage-removal')->assertExitCode(0);

        Queue::assertNotPushed(ProcessTrialCompleteStageRemovalJob::class);
    }

    public function test_does_not_dispatch_when_status_not_running_healthy_claimed(): void
    {
        $storeApp = $this->createStoreApp();
        $this->createInstance(
            $storeApp,
            status: PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED,
            trialEndsAt: now()->subHour(),
        );

        $this->artisan('polydock:dispatch-trial-complete-stage-removal')->assertExitCode(0);

        Queue::assertNotPushed(ProcessTrialCompleteStageRemovalJob::class);
    }

    public function test_respects_per_run_cap(): void
    {
        config()->set('polydock.max_per_run_dispatch_trial_complete_stage_removal', 2);

        $storeApp = $this->createStoreApp();
        for ($i = 0; $i < 4; $i++) {
            $this->createInstance($storeApp, trialEndsAt: now()->subHour());
        }

        $this->artisan('polydock:dispatch-trial-complete-stage-removal')->assertExitCode(0);

        Queue::assertPushed(ProcessTrialCompleteStageRemovalJob::class, 2);
    }
}
