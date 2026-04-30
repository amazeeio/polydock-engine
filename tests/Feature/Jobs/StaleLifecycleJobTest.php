<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessPolydockAppInstanceJobs\Claim\ClaimJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Create\CreateJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Deploy\DeployJob;
use App\Jobs\ProcessPolydockAppInstanceJobs\Remove\RemoveJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;
use FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp;
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
