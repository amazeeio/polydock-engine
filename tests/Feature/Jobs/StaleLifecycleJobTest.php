<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessPolydockAppInstanceJobs\Claim\ClaimJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Create\CreateJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Deploy\DeployJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Deploy\PollDeploymentJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Remove\RemoveJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Apps\Generic\PolydockAiApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaleLifecycleJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_job_skips_when_instance_already_advanced_to_running(): void
    {
        $appInstance = $this->createAppInstance(
            'stale-claim-job-test',
            PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
            'Already running',
        );

        (new ClaimJob($appInstance->id))->handle();

        $appInstance->refresh();

        $this->assertSame(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $appInstance->status);
        $this->assertSame('Already running', $appInstance->status_message);
    }

    public function test_claim_job_skips_when_instance_already_advanced_to_upgrade_status(): void
    {
        // A stale ClaimJob can land in the queue after the instance has
        // already been claimed AND begun an in-place upgrade. The skip
        // logic must recognise upgrade statuses as "advanced past claim".
        $appInstance = $this->createAppInstance(
            'stale-claim-during-upgrade',
            PolydockAppInstanceStatus::PENDING_PRE_UPGRADE,
            'Upgrade pending',
        );

        (new ClaimJob($appInstance->id))->handle();

        $appInstance->refresh();

        $this->assertSame(PolydockAppInstanceStatus::PENDING_PRE_UPGRADE, $appInstance->status);
        $this->assertSame('Upgrade pending', $appInstance->status_message);
    }

    public function test_create_job_skips_when_instance_already_advanced_to_deploy(): void
    {
        $appInstance = $this->createAppInstance(
            'stale-create-job-test',
            PolydockAppInstanceStatus::PENDING_DEPLOY,
            'Ready to deploy',
        );

        (new CreateJob($appInstance->id))->handle();

        $appInstance->refresh();

        $this->assertSame(PolydockAppInstanceStatus::PENDING_DEPLOY, $appInstance->status);
        $this->assertSame('Ready to deploy', $appInstance->status_message);
    }

    public function test_deploy_job_skips_when_instance_already_advanced_to_running(): void
    {
        $appInstance = $this->createAppInstance(
            'stale-deploy-job-test',
            PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED,
            'Running unclaimed',
        );

        (new DeployJob($appInstance->id))->handle();

        $appInstance->refresh();

        $this->assertSame(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $appInstance->status);
        $this->assertSame('Running unclaimed', $appInstance->status_message);
    }

    public function test_remove_job_skips_when_instance_already_advanced_to_post_remove(): void
    {
        $appInstance = $this->createAppInstance(
            'stale-remove-job-test',
            PolydockAppInstanceStatus::PENDING_POST_REMOVE,
            'Awaiting post-remove',
        );

        (new RemoveJob($appInstance->id))->handle();

        $appInstance->refresh();

        $this->assertSame(PolydockAppInstanceStatus::PENDING_POST_REMOVE, $appInstance->status);
        $this->assertSame('Awaiting post-remove', $appInstance->status_message);
    }

    public function test_create_job_throws_status_flow_exception_when_status_is_not_a_known_progression(): void
    {
        // Sanity check: the skip logic must only short-circuit when the
        // current status is genuinely *after* the expected status. An
        // unrelated/earlier status must still raise the flow exception.
        $appInstance = $this->createAppInstance(
            'unrelated-status-create-job',
            PolydockAppInstanceStatus::NEW,
            'Brand new',
        );

        $this->expectException(PolydockAppInstanceStatusFlowException::class);

        (new CreateJob($appInstance->id))->handle();
    }

    public function test_create_job_does_not_skip_when_instance_is_in_the_same_create_stage(): void
    {
        // Stages are per-phase: PENDING_CREATE, CREATE_RUNNING, and
        // CREATE_COMPLETED all live in the "create" stage. A stale job whose
        // expected status is PENDING_CREATE must NOT be silently skipped just
        // because the instance has progressed to CREATE_RUNNING / COMPLETED
        // within the same stage. (Dedup is the responsibility of
        // WithoutOverlapping, not the skip logic.)
        $appInstance = $this->createAppInstance(
            'same-stage-create-job',
            PolydockAppInstanceStatus::CREATE_COMPLETED,
            'Already completed create',
        );

        $this->expectException(PolydockAppInstanceStatusFlowException::class);

        (new CreateJob($appInstance->id))->handle();
    }

    public function test_claim_job_skips_when_instance_is_in_a_sibling_running_state(): void
    {
        // ClaimJob's expected status is PENDING_POLYDOCK_CLAIM. All
        // RUNNING_* statuses sit strictly after the claim stage in the
        // lifecycle order, so a stale ClaimJob must be skipped even when
        // the instance has only advanced to RUNNING_HEALTHY_UNCLAIMED
        // rather than a later phase like upgrade or remove.
        $appInstance = $this->createAppInstance(
            'sibling-running-claim-job',
            PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED,
            'Running unclaimed, awaiting claim',
        );

        (new ClaimJob($appInstance->id))->handle();

        $appInstance->refresh();

        $this->assertSame(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $appInstance->status);
        $this->assertSame('Running unclaimed, awaiting claim', $appInstance->status_message);
    }

    public function test_deploy_job_does_not_skip_when_instance_is_in_the_same_deploy_stage(): void
    {
        // PENDING_DEPLOY, DEPLOY_RUNNING, DEPLOY_COMPLETED all share the
        // deploy stage. A stale DeployJob targeting PENDING_DEPLOY must not
        // be silently skipped when the instance is at DEPLOY_RUNNING.
        $appInstance = $this->createAppInstance(
            'same-stage-deploy-job',
            PolydockAppInstanceStatus::DEPLOY_RUNNING,
            'Deploy in progress',
        );

        $this->expectException(PolydockAppInstanceStatusFlowException::class);

        (new DeployJob($appInstance->id))->handle();
    }

    public function test_poll_deployment_job_skips_when_instance_already_advanced_to_post_deploy(): void
    {
        $appInstance = $this->createAppInstance(
            'stale-poll-deploy-job-test',
            PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED,
            'Post deploy completed',
        );

        (new PollDeploymentJob($appInstance->id))->handle();

        $appInstance->refresh();

        $this->assertSame(PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED, $appInstance->status);
        $this->assertSame('Post deploy completed', $appInstance->status_message);
    }

    private function createAppInstance(
        string $name,
        PolydockAppInstanceStatus $status,
        string $statusMessage,
    ): PolydockAppInstance {
        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
        $userGroup = UserGroup::factory()->create();

        return PolydockAppInstance::createQuietly([
            'polydock_store_app_id' => $storeApp->id,
            'user_group_id' => $userGroup->id,
            'name' => $name,
            'app_type' => PolydockAiApp::class,
            'status' => $status,
            'status_message' => $statusMessage,
        ]);
    }
}
