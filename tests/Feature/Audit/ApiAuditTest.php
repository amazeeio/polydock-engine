<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Enums\UserGroupRoleEnum;
use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Models\UserGroup;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PolydockStoreApp $storeApp;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);

        $this->user = User::factory()->create();

        $store = PolydockStore::create([
            'name' => 'Test Store',
            'status' => 'public',
            'listed_in_marketplace' => true,
            'lagoon_deploy_region_id_ext' => 1,
            'lagoon_deploy_project_prefix' => 'test-prefix',
            'lagoon_deploy_organization_id_ext' => 1,
            'lagoon_deploy_group_name' => 'test-group',
        ]);

        $this->storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
    }

    public function test_create_instance_records_audit_with_actor(): void
    {
        $role = Role::findOrCreate('service-account', config('auth.defaults.guard'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->assignRole($role);

        Sanctum::actingAs($this->user, ['instances.write']);

        $response = $this->postJson('/api/instance', [
            'email' => 'test@example.com',
            'storeAppId' => $this->storeApp->uuid,
        ]);

        $response->assertCreated();

        $activity = Activity::where('description', 'Instance provisioned via API')->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->user->id, $activity->causer_id);
        $this->assertEquals('api.instance.create', $activity->properties['action']);
        $this->assertEquals('test@example.com', $activity->properties['on_behalf_of']);
    }

    public function test_delete_instance_records_audit(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $group = UserGroup::create(['name' => 'Delete Audit Group']);
        $this->user->groups()->attach($group->id, ['role' => UserGroupRoleEnum::MEMBER->value]);

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $group->id,
        ]);
        $instance->setStatus(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED);
        $instance->save();

        Activity::query()->delete();

        $response = $this->deleteJson("/api/instance/{$instance->uuid}");

        $response->assertOk();

        $activity = Activity::where('description', 'Instance deletion initiated via API')->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->user->id, $activity->causer_id);
        $this->assertEquals('api.instance.delete', $activity->properties['action']);
        $this->assertEquals($instance->uuid, $activity->properties['instance_uuid']);
    }

    public function test_assign_instance_to_group_records_audit(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $originalGroup = UserGroup::create(['name' => 'Original Audit Group']);
        $targetGroup = UserGroup::create(['name' => 'Target Audit Group']);
        $this->user->groups()->syncWithoutDetaching([
            $originalGroup->id => ['role' => UserGroupRoleEnum::ADMIN->value],
            $targetGroup->id => ['role' => UserGroupRoleEnum::MEMBER->value],
        ]);

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $originalGroup->id,
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);

        Activity::query()->delete();

        $response = $this->patchJson("/api/instance/{$instance->uuid}/group", [
            'group_id' => $targetGroup->id,
        ]);

        $response->assertOk();

        $activity = Activity::where('description', 'Instance reassigned to group via API')->first();

        $this->assertNotNull($activity);
        $this->assertEquals('api.instance.reassign_group', $activity->properties['action']);
        $this->assertEquals($originalGroup->id, $activity->properties['old_group_id']);
        $this->assertEquals($targetGroup->id, $activity->properties['new_group_id']);
    }

    public function test_create_group_records_audit(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        Activity::query()->delete();

        $response = $this->postJson('/api/groups', [
            'name' => 'Audit Workspace',
        ]);

        $response->assertCreated();

        $activity = Activity::where('description', 'Group created via API')->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->user->id, $activity->causer_id);
        $this->assertEquals('api.group.create', $activity->properties['action']);
        $this->assertEquals($this->user->email, $activity->properties['owner_email']);
    }

    public function test_service_account_token_id_is_captured_in_audit_properties(): void
    {
        $role = Role::findOrCreate('service-account', config('auth.defaults.guard'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->assignRole($role);

        $token = $this->user->createToken('moad-integration', ['instances.write']);
        $this->user->withAccessToken($token->accessToken);

        Sanctum::actingAs($this->user, ['instances.write']);

        $response = $this->postJson('/api/instance', [
            'email' => 'token-test@example.com',
            'storeAppId' => $this->storeApp->uuid,
        ]);

        $response->assertCreated();

        $activity = Activity::where('description', 'Instance provisioned via API')->first();

        $this->assertNotNull($activity);
        $this->assertTrue($activity->properties['is_service_account']);
    }
}
