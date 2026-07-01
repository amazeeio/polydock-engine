<?php

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\PolydockAppInstanceResource\Pages\ListPolydockAppInstances;
use App\Filament\Admin\Resources\PolydockDeploymentRunResource;
use App\Filament\Admin\Resources\PolydockDeploymentRunResource\Pages\ListPolydockDeploymentRuns;
use App\Filament\Admin\Resources\PolydockStoreAppResource\Pages\EditPolydockStoreApp;
use App\Filament\Admin\Resources\UserGroupResource\Pages\EditUserGroup;
use App\Models\PolydockAppInstance;
use App\Models\PolydockDeploymentRun;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Models\UserGroup;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Services\LagoonClientService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Doubles\FakeLagoonClient;
use Tests\Doubles\FakeLagoonClientService;
use Tests\TestCase;

class DeploymentAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FakeLagoonClient $client;

    private PolydockStoreApp $storeApp;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->client = new FakeLagoonClient;
        $this->app->instance(LagoonClientService::class, new FakeLagoonClientService($this->client));

        $this->admin = User::factory()->create();
        Role::findOrCreate('super_admin', config('auth.defaults.guard'));
        $this->admin->assignRole('super_admin');

        $store = PolydockStore::factory()->create();
        $this->storeApp = PolydockStoreApp::factory()->create(['polydock_store_id' => $store->id]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function makeInstance(string $project = 'proj-a'): PolydockAppInstance
    {
        $instance = new PolydockAppInstance;
        $instance->fill([
            'polydock_store_app_id' => $this->storeApp->id,
            'name' => $project,
            'app_type' => 'test-app',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);
        $instance->uuid = (string) Str::uuid();
        $instance->data = ['lagoon-project-name' => $project, 'lagoon-deploy-branch' => 'main'];
        $instance->saveQuietly();

        return $instance->refresh();
    }

    public function test_admin_can_bulk_redeploy_from_instance_list(): void
    {
        $instance = $this->makeInstance();

        $this->actingAs($this->admin);

        Livewire::test(ListPolydockAppInstances::class)
            ->callTableBulkAction('redeploy', [$instance->getKey()]);

        $this->assertSame(1, PolydockDeploymentRun::count());
        $this->assertCount(1, $this->client->bulkCalls);
        $this->assertNotNull($instance->refresh()->deployment_run_id);
    }

    public function test_manage_gate_respects_role_and_permission(): void
    {
        $this->actingAs($this->admin);
        $this->assertTrue(PolydockDeploymentRun::currentUserCanManage());

        $plain = User::factory()->create();
        $this->actingAs($plain);
        $this->assertFalse(PolydockDeploymentRun::currentUserCanManage());
    }

    public function test_deployment_dashboard_visible_only_to_managers(): void
    {
        $this->actingAs($this->admin);
        $this->assertTrue(PolydockDeploymentRunResource::canViewAny());

        Livewire::test(ListPolydockDeploymentRuns::class)->assertSuccessful();

        $plain = User::factory()->create();
        $this->actingAs($plain);
        $this->assertFalse(PolydockDeploymentRunResource::canViewAny());
    }

    public function test_store_app_form_exposes_cadence_fields(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(EditPolydockStoreApp::class, ['record' => $this->storeApp->getRouteKey()])
            ->assertFormFieldExists('redeploy_enabled')
            ->assertFormFieldExists('redeploy_interval_days')
            ->assertFormFieldExists('beta_redeploy_interval_days');
    }

    public function test_user_group_is_beta_can_be_saved(): void
    {
        $group = UserGroup::factory()->create(['is_beta' => false]);

        $this->actingAs($this->admin);

        Livewire::test(EditUserGroup::class, ['record' => $group->getRouteKey()])
            ->fillForm(['is_beta' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($group->refresh()->is_beta);
    }
}
