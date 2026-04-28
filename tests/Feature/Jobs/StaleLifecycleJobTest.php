<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessPolydockAppInstanceJobs\Claim\ClaimJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaleLifecycleJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_job_skips_when_instance_already_advanced_to_running(): void
    {
        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
        $userGroup = UserGroup::factory()->create();

        $appInstance = PolydockAppInstance::createQuietly([
            'polydock_store_app_id' => $storeApp->id,
            'user_group_id' => $userGroup->id,
            'name' => 'stale-claim-job-test',
            'app_type' => PolydockAiApp::class,
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
            'status_message' => 'Already running',
            'data' => [],
        ]);

        $job = new ClaimJob($appInstance->id);
        $job->handle();

        $appInstance->refresh();

        $this->assertSame(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $appInstance->status);
        $this->assertSame('Already running', $appInstance->status_message);
    }
}
