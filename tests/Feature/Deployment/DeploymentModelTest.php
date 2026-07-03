<?php

namespace Tests\Feature\Deployment;

use App\Enums\PolydockDeploymentRunStatusEnum;
use App\Enums\PolydockDeploymentRunTriggerSourceEnum;
use App\Models\PolydockAppInstance;
use App\Models\PolydockDeploymentRun;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeploymentModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeStoreApp(): PolydockStoreApp
    {
        $store = PolydockStore::factory()->create();

        return PolydockStoreApp::factory()->create(['polydock_store_id' => $store->id]);
    }

    private function makeInstance(PolydockStoreApp $app, array $attributes = []): PolydockAppInstance
    {
        $instance = new PolydockAppInstance;
        $instance->fill(array_merge([
            'polydock_store_app_id' => $app->id,
            'name' => 'test-instance',
            'app_type' => 'test-app',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ], $attributes));
        $instance->saveQuietly();

        return $instance->refresh();
    }

    public function test_deployment_run_is_created_with_uuid_and_relations(): void
    {
        $app = $this->makeStoreApp();
        $run = PolydockDeploymentRun::factory()->create(['polydock_store_app_id' => $app->id]);

        $this->assertNotEmpty($run->uuid);
        $this->assertTrue($run->storeApp->is($app));
        $this->assertInstanceOf(PolydockDeploymentRunStatusEnum::class, $run->status);
        $this->assertInstanceOf(PolydockDeploymentRunTriggerSourceEnum::class, $run->trigger_source);

        $instance = $this->makeInstance($app, ['deployment_run_id' => $run->id]);

        $this->assertTrue($run->instances()->whereKey($instance->getKey())->exists());
        $this->assertTrue($instance->deploymentRun->is($run));
    }

    public function test_effective_redeploy_interval_days_resolves_beta_override(): void
    {
        $app = $this->makeStoreApp();

        $app->redeploy_interval_days = 7;
        $app->beta_redeploy_interval_days = 2;

        $this->assertSame(7, $app->effectiveRedeployIntervalDays(false));
        $this->assertSame(2, $app->effectiveRedeployIntervalDays(true));

        // Beta with no override falls back to default.
        $app->beta_redeploy_interval_days = null;
        $this->assertSame(7, $app->effectiveRedeployIntervalDays(true));

        // Neither configured → null (do not auto-redeploy).
        $app->redeploy_interval_days = null;
        $this->assertNull($app->effectiveRedeployIntervalDays(false));
        $this->assertNull($app->effectiveRedeployIntervalDays(true));
    }

    public function test_instance_deploy_columns_are_cast(): void
    {
        $app = $this->makeStoreApp();
        $instance = $this->makeInstance($app, [
            'last_deployment_name' => 'lagoon-build-abc',
            'last_deployment_status' => 'complete',
            'last_deployed_at' => now(),
            'next_redeploy_at' => now()->addDays(7),
        ]);

        $this->assertInstanceOf(CarbonInterface::class, $instance->last_deployed_at);
        $this->assertInstanceOf(CarbonInterface::class, $instance->next_redeploy_at);
        $this->assertSame('lagoon-build-abc', $instance->last_deployment_name);
    }

    public function test_user_group_is_beta_is_boolean(): void
    {
        $group = UserGroup::factory()->create(['is_beta' => true]);
        $this->assertTrue($group->refresh()->is_beta);

        $default = UserGroup::factory()->create();
        $this->assertFalse($default->refresh()->is_beta);
    }

    public function test_redeploy_eligibility_and_in_flight_detection(): void
    {
        $app = $this->makeStoreApp();

        $eligible = $this->makeInstance($app, ['status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED]);
        $this->assertTrue($eligible->isRedeployEligible());

        $ineligible = $this->makeInstance($app, ['status' => PolydockAppInstanceStatus::PENDING_DEPLOY]);
        $this->assertFalse($ineligible->isRedeployEligible());

        $runningRun = PolydockDeploymentRun::factory()->create([
            'polydock_store_app_id' => $app->id,
            'status' => PolydockDeploymentRunStatusEnum::RUNNING,
        ]);
        $inFlight = $this->makeInstance($app, ['deployment_run_id' => $runningRun->id]);
        $this->assertTrue($inFlight->hasInFlightDeployment());

        $doneRun = PolydockDeploymentRun::factory()->completed()->create(['polydock_store_app_id' => $app->id]);
        $settled = $this->makeInstance($app, ['deployment_run_id' => $doneRun->id]);
        $this->assertFalse($settled->hasInFlightDeployment());
    }
}
