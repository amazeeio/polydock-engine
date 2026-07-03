<?php

namespace Tests\Feature\Deployment;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Enums\PolydockDeploymentRunTriggerSourceEnum;
use App\Models\PolydockAppInstance;
use App\Models\PolydockDeploymentRun;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Services\LagoonClientService;
use App\Services\PolydockDeploymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Doubles\FakeLagoonClient;
use Tests\Doubles\FakeLagoonClientService;
use Tests\TestCase;

class PollDeploymentRunJobTest extends TestCase
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

    private function makeRun(): PolydockDeploymentRun
    {
        return PolydockDeploymentRun::factory()->create([
            'polydock_store_app_id' => $this->storeApp->id,
            'status' => PolydockDeploymentRunStatusEnum::RUNNING,
            'lagoon_bulk_id' => 'bulk-test-1',
            'trigger_source' => PolydockDeploymentRunTriggerSourceEnum::SCHEDULED,
            'poll_attempts' => 0,
        ]);
    }

    private function makeInstance(PolydockDeploymentRun $run, string $project): PolydockAppInstance
    {
        $instance = new PolydockAppInstance;
        $instance->fill([
            'polydock_store_app_id' => $this->storeApp->id,
            'name' => $project,
            'app_type' => 'test-app',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
            'deployment_run_id' => $run->id,
        ]);
        $instance->data = ['lagoon-project-name' => $project, 'lagoon-deploy-branch' => 'main'];
        $instance->saveQuietly();

        return $instance->refresh();
    }

    public function test_building_then_complete_updates_instance_and_run(): void
    {
        $run = $this->makeRun();
        $instance = $this->makeInstance($run, 'proj-a');

        $this->client->deploymentResponses = [
            [FakeLagoonClient::deployment('proj-a', 'main', 'building', 'lagoon-build-1')],
            [FakeLagoonClient::deployment('proj-a', 'main', 'complete', 'lagoon-build-1')],
        ];

        // First poll: still building → run stays RUNNING.
        $this->service()->pollRun($run);
        $instance->refresh();
        $run->refresh();
        $this->assertSame('building', $instance->last_deployment_status);
        $this->assertSame(PolydockDeploymentRunStatusEnum::RUNNING, $run->status);
        $this->assertNull($instance->last_deployed_at);

        // Second poll: complete → run COMPLETED, deployed timestamp set.
        $this->service()->pollRun($run);
        $instance->refresh();
        $run->refresh();
        $this->assertSame('complete', $instance->last_deployment_status);
        $this->assertNotNull($instance->last_deployed_at);
        $this->assertSame(PolydockDeploymentRunStatusEnum::COMPLETED, $run->status);
        $this->assertSame(1, $run->success_count);
        $this->assertSame(2, $run->poll_attempts);
    }

    public function test_failed_build_does_not_change_instance_lifecycle_status(): void
    {
        $run = $this->makeRun();
        $instance = $this->makeInstance($run, 'proj-a');

        $this->client->deploymentResponses = [
            [FakeLagoonClient::deployment('proj-a', 'main', 'failed', 'lagoon-build-1')],
        ];

        $this->service()->pollRun($run);
        $instance->refresh();
        $run->refresh();

        $this->assertSame(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $instance->status);
        $this->assertSame('failed', $instance->last_deployment_status);
        $this->assertSame(1, $run->failed_count);
        $this->assertSame(PolydockDeploymentRunStatusEnum::FAILED, $run->status);
    }

    public function test_mixed_results_are_partial_failed(): void
    {
        $run = $this->makeRun();
        $ok = $this->makeInstance($run, 'proj-ok');
        $bad = $this->makeInstance($run, 'proj-bad');

        $this->client->deploymentResponses = [[
            FakeLagoonClient::deployment('proj-ok', 'main', 'complete'),
            FakeLagoonClient::deployment('proj-bad', 'main', 'failed'),
        ]];

        $this->service()->pollRun($run);
        $run->refresh();

        $this->assertSame(1, $run->success_count);
        $this->assertSame(1, $run->failed_count);
        $this->assertSame(PolydockDeploymentRunStatusEnum::PARTIAL_FAILED, $run->status);
        unset($ok, $bad);
    }

    public function test_poll_gives_up_after_max_attempts(): void
    {
        config(['polydock.deploy.max_poll_attempts' => 1]);
        $run = $this->makeRun();
        $this->makeInstance($run, 'proj-a');

        // Never terminal: always "building".
        $this->client->lastDeployments = [FakeLagoonClient::deployment('proj-a', 'main', 'building')];

        $this->service()->pollRun($run);
        $run->refresh();

        $this->assertSame(1, $run->poll_attempts);
        $this->assertSame(PolydockDeploymentRunStatusEnum::FAILED, $run->status);
    }

    public function test_terminal_run_is_not_polled(): void
    {
        $run = PolydockDeploymentRun::factory()->completed()->create([
            'polydock_store_app_id' => $this->storeApp->id,
            'poll_attempts' => 3,
        ]);

        $this->service()->pollRun($run);

        $this->assertSame(3, $run->refresh()->poll_attempts);
    }
}
