<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Enums\UserGroupRoleEnum;
use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Jobs\ProcessPolydockAppInstanceJobs\Claim\ClaimJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Create\CreateJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Create\PostCreateJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Create\PreCreateJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Deploy\DeployJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Deploy\PostDeployJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Deploy\PreDeployJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\ProgressToNextStageJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Purge\ProcessProjectPurgeJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Remove\PostRemoveJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Remove\PreRemoveJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Remove\RemoveJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Upgrade\PostUpgradeJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Upgrade\PreUpgradeJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Upgrade\UpgradeJob;
use App\Listeners\ProcessPolydockAppInstanceStatusChange;
use App\Mail\AppInstanceReadyMail;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserRemoteRegistration;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The status-change listener is the lifecycle's dispatch table: a mis-mapped
 * status silently strands instances mid-pipeline, so the full map is pinned
 * here, along with the two statuses that carry real behavior (ready-email
 * flow and force purge).
 */
class ProcessPolydockAppInstanceStatusChangeTest extends TestCase
{
    use RefreshDatabase;

    private function makeInstance(PolydockAppInstanceStatus $status, array $data = []): PolydockAppInstance
    {
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => PolydockStore::factory()->create()->id,
        ]);

        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'listener-test-'.$status->value;
        $instance->app_type = 'test-app';
        $instance->status = $status;
        $instance->data = $data;
        $instance->saveQuietly();

        return $instance;
    }

    private function handle(PolydockAppInstance $instance): void
    {
        (new ProcessPolydockAppInstanceStatusChange)->handle(
            new PolydockAppInstanceStatusChanged($instance, PolydockAppInstanceStatus::NEW),
        );
    }

    public function test_every_pending_status_dispatches_its_stage_job_on_its_queue(): void
    {
        $map = [
            PolydockAppInstanceStatus::PENDING_PRE_CREATE->value => [PreCreateJob::class, 'polydock-app-instance-processing-create'],
            PolydockAppInstanceStatus::PENDING_CREATE->value => [CreateJob::class, 'polydock-app-instance-processing-create'],
            PolydockAppInstanceStatus::PENDING_POST_CREATE->value => [PostCreateJob::class, 'polydock-app-instance-processing-create'],
            PolydockAppInstanceStatus::PENDING_PRE_DEPLOY->value => [PreDeployJob::class, 'polydock-app-instance-processing-deploy'],
            PolydockAppInstanceStatus::PENDING_DEPLOY->value => [DeployJob::class, 'polydock-app-instance-processing-deploy'],
            PolydockAppInstanceStatus::PENDING_POST_DEPLOY->value => [PostDeployJob::class, 'polydock-app-instance-processing-deploy'],
            PolydockAppInstanceStatus::PENDING_PRE_REMOVE->value => [PreRemoveJob::class, 'polydock-app-instance-processing-remove'],
            PolydockAppInstanceStatus::PENDING_REMOVE->value => [RemoveJob::class, 'polydock-app-instance-processing-remove'],
            PolydockAppInstanceStatus::PENDING_POST_REMOVE->value => [PostRemoveJob::class, 'polydock-app-instance-processing-remove'],
            PolydockAppInstanceStatus::PENDING_PURGE->value => [ProcessProjectPurgeJob::class, 'polydock-app-instance-processing-remove'],
            PolydockAppInstanceStatus::PENDING_PRE_UPGRADE->value => [PreUpgradeJob::class, 'polydock-app-instance-processing-upgrade'],
            PolydockAppInstanceStatus::PENDING_UPGRADE->value => [UpgradeJob::class, 'polydock-app-instance-processing-upgrade'],
            PolydockAppInstanceStatus::PENDING_POST_UPGRADE->value => [PostUpgradeJob::class, 'polydock-app-instance-processing-upgrade'],
            PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM->value => [ClaimJob::class, 'polydock-app-instance-processing-claim'],
        ];

        foreach ($map as $statusValue => [$job, $queue]) {
            // Fresh fake per status so each assertion is independent — an
            // accumulating fake would mask two statuses mis-mapped to the
            // same job class.
            Queue::fake();

            $this->handle($this->makeInstance(PolydockAppInstanceStatus::from($statusValue)));

            Queue::assertPushedOn($queue, $job);
            Queue::assertCount(1); // exactly one job per status change
        }
    }

    public function test_completed_statuses_dispatch_progress_to_next_stage(): void
    {
        Queue::fake();

        $this->handle($this->makeInstance(PolydockAppInstanceStatus::PRE_CREATE_COMPLETED));

        Queue::assertPushedOn('polydock-app-instance-processing-progress-to-next-stage', ProgressToNextStageJob::class);
    }

    public function test_unmapped_status_dispatches_nothing(): void
    {
        Queue::fake();

        $this->handle($this->makeInstance(PolydockAppInstanceStatus::RUNNING_UNHEALTHY));

        Queue::assertNothingPushed();
    }

    public function test_claimed_status_marks_registration_success_and_queues_ready_email(): void
    {
        Queue::fake();
        Mail::fake();

        $owner = User::factory()->create();
        $group = UserGroup::factory()->create();
        $group->users()->attach($owner->id, ['role' => UserGroupRoleEnum::OWNER->value]);

        $instance = $this->makeInstance(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED);
        $instance->user_group_id = $group->id;
        $instance->app_one_time_login_url = 'https://example.com/login/once';
        $instance->saveQuietly();

        $registration = UserRemoteRegistration::create([
            'email' => $owner->email,
            'status' => UserRemoteRegistrationStatusEnum::PENDING,
            'request_data' => ['email' => $owner->email],
        ]);
        $registration->polydock_app_instance_id = $instance->id;
        $registration->save();

        $this->handle($instance->fresh());

        $registration->refresh();
        $this->assertSame(UserRemoteRegistrationStatusEnum::SUCCESS, $registration->status);

        Mail::assertQueued(AppInstanceReadyMail::class, fn ($mail) => $mail->hasTo($owner->email));
    }

    public function test_manual_claim_rerun_can_skip_the_ready_email_flow(): void
    {
        Queue::fake();
        Mail::fake();

        $instance = $this->makeInstance(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, [
            'manual_hook_rerun' => ['hook' => 'claim', 'skip_ready_notification' => true],
        ]);

        $this->handle($instance);

        Mail::assertNothingQueued();
        // The rerun marker is consumed so the next genuine claim behaves normally.
        $this->assertArrayNotHasKey('manual_hook_rerun', $instance->fresh()->data ?? []);
    }

    public function test_removed_with_force_purge_requested_transitions_to_pending_purge(): void
    {
        Queue::fake();

        $instance = $this->makeInstance(PolydockAppInstanceStatus::REMOVED);
        $instance->force_purge_requested_at = now();
        $instance->purge_last_attempted_at = null;
        $instance->saveQuietly();

        $this->handle($instance);

        $instance->refresh();
        $this->assertSame(PolydockAppInstanceStatus::PENDING_PURGE, $instance->status);
        $this->assertNotNull($instance->purge_eligible_at);
    }

    public function test_removed_purge_retry_is_left_to_the_scheduled_command(): void
    {
        Queue::fake();

        $instance = $this->makeInstance(PolydockAppInstanceStatus::REMOVED);
        $instance->force_purge_requested_at = now();
        $instance->purge_last_attempted_at = now(); // a retry coming back from the purge job
        $instance->saveQuietly();

        $this->handle($instance);

        $this->assertSame(PolydockAppInstanceStatus::REMOVED, $instance->fresh()->status);
    }
}
