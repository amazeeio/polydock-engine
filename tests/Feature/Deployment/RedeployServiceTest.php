<?php

namespace Tests\Feature\Deployment;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Enums\PolydockDeploymentRunTriggerSourceEnum;
use App\Jobs\PollDeploymentRunJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Services\LagoonClientService;
use App\Services\PolydockDeploymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Doubles\FakeLagoonClient;
use Tests\Doubles\FakeLagoonClientService;
use Tests\TestCase;

class RedeployServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeLagoonClient $client;

    private PolydockStoreApp $storeApp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakeLagoonClient;
        $this->app->instance(LagoonClientService::class, new FakeLagoonClientService($this->client));

        $store = PolydockStore::factory()->create();
        $this->storeApp = PolydockStoreApp::factory()->create(['polydock_store_id' => $store->id]);
    }

    private function service(): PolydockDeploymentService
    {
        return app(PolydockDeploymentService::class);
    }

    private function makeInstance(
        PolydockAppInstanceStatus $status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        string $project = 'proj-a',
        array $attributes = [],
    ): PolydockAppInstance {
        $instance = new PolydockAppInstance;
        $instance->fill(array_merge([
            'polydock_store_app_id' => $this->storeApp->id,
            'name' => $project,
            'app_type' => 'test-app',
            'status' => $status,
        ], $attributes));
        $instance->data = ['lagoon-project-name' => $project, 'lagoon-deploy-branch' => 'main'];
        $instance->saveQuietly();

        return $instance->refresh();
    }

    public function test_ineligible_instances_are_skipped(): void
    {
        Queue::fake();
        $eligible = $this->makeInstance(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, 'proj-eligible');
        $ineligible = $this->makeInstance(PolydockAppInstanceStatus::PENDING_DEPLOY, 'proj-ineligible');

        $run = $this->service()->redeploy(
            [$eligible, $ineligible],
            PolydockDeploymentRunTriggerSourceEnum::MANUAL,
        );

        $this->assertNotNull($run);
        $this->assertSame(1, $run->total_count);
        $this->assertCount(1, $this->client->bulkCalls);
        $this->assertCount(1, $this->client->bulkCalls[0]['environments']);
        $this->assertSame('proj-eligible', $this->client->bulkCalls[0]['environments'][0]['project']);

        $this->assertSame($run->id, $eligible->refresh()->deployment_run_id);
        $this->assertNull($ineligible->refresh()->deployment_run_id);
    }

    public function test_returns_null_when_nothing_deployable(): void
    {
        Queue::fake();
        $ineligible = $this->makeInstance(PolydockAppInstanceStatus::PENDING_DEPLOY);

        $run = $this->service()->redeploy([$ineligible], PolydockDeploymentRunTriggerSourceEnum::SCHEDULED);

        $this->assertNull($run);
        $this->assertCount(0, $this->client->bulkCalls);
    }

    public function test_bulk_deploy_persists_run_and_dispatches_poll(): void
    {
        Queue::fake();
        $a = $this->makeInstance(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'proj-a');
        $b = $this->makeInstance(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, 'proj-b');

        $run = $this->service()->redeploy([$a, $b], PolydockDeploymentRunTriggerSourceEnum::SCHEDULED);

        $this->assertNotNull($run);
        $this->assertSame('bulk-test-1', $run->lagoon_bulk_id);
        $this->assertSame(2, $run->total_count);
        $this->assertSame(PolydockDeploymentRunStatusEnum::RUNNING, $run->status);
        $this->assertNotNull($a->refresh()->last_deploy_triggered_at);
        $this->assertNotNull($b->refresh()->last_deploy_triggered_at);

        Queue::assertPushed(PollDeploymentRunJob::class, fn ($job) => $job->deploymentRunId === $run->id);
    }

    public function test_trigger_failure_marks_run_failed_and_does_not_claim_instances(): void
    {
        Queue::fake();
        $this->client->throwOnDeploy = true;
        $instance = $this->makeInstance();

        $run = $this->service()->redeploy([$instance], PolydockDeploymentRunTriggerSourceEnum::MANUAL);

        $this->assertNotNull($run);
        $this->assertSame(PolydockDeploymentRunStatusEnum::FAILED, $run->status);
        $this->assertNull($instance->refresh()->deployment_run_id);
        Queue::assertNotPushed(PollDeploymentRunJob::class);
    }

    public function test_in_flight_instance_is_not_deployed_twice(): void
    {
        Queue::fake();
        $instance = $this->makeInstance();

        $first = $this->service()->redeploy([$instance], PolydockDeploymentRunTriggerSourceEnum::MANUAL);
        $this->assertNotNull($first);

        // Second call while the first run is still RUNNING must be a no-op.
        $second = $this->service()->redeploy([$instance->refresh()], PolydockDeploymentRunTriggerSourceEnum::MANUAL);

        $this->assertNull($second);
        $this->assertCount(1, $this->client->bulkCalls);
    }
}
