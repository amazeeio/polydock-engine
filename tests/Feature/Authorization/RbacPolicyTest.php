<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Enums\UserGroupRoleEnum;
use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent lifecycle listeners from dispatching jobs.
        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);
    }

    public function test_group_role_hierarchy_is_enforced(): void
    {
        $group = UserGroup::factory()->create();

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $viewer = User::factory()->create();

        $owner->groups()->attach($group->id, ['role' => UserGroupRoleEnum::OWNER->value]);
        $admin->groups()->attach($group->id, ['role' => UserGroupRoleEnum::ADMIN->value]);
        $member->groups()->attach($group->id, ['role' => UserGroupRoleEnum::MEMBER->value]);
        $viewer->groups()->attach($group->id, ['role' => UserGroupRoleEnum::VIEWER->value]);

        $this->assertTrue($owner->can('update', $group));
        $this->assertTrue($admin->can('update', $group));
        $this->assertFalse($member->can('update', $group));
        $this->assertFalse($viewer->can('update', $group));

        $this->assertTrue($owner->can('delete', $group));
        $this->assertFalse($admin->can('delete', $group));
        $this->assertFalse($member->can('delete', $group));
    }

    public function test_super_admin_bypasses_group_checks(): void
    {
        $group = UserGroup::factory()->create();
        $user = User::factory()->create();

        Role::findOrCreate('super_admin', config('auth.defaults.guard'));
        $user->assignRole('super_admin');

        $this->assertTrue($user->can('view', $group));
        $this->assertTrue($user->can('update', $group));
        $this->assertTrue($user->can('delete', $group));
    }

    public function test_instance_access_is_scoped_to_group_membership(): void
    {
        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);

        $groupA = UserGroup::factory()->create();
        $groupB = UserGroup::factory()->create();

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $userA->groups()->attach($groupA->id, ['role' => UserGroupRoleEnum::MEMBER->value]);
        $userB->groups()->attach($groupB->id, ['role' => UserGroupRoleEnum::MEMBER->value]);

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $storeApp->id,
            'user_group_id' => $groupA->id,
            'name' => 'test-instance',
        ]);

        $this->assertTrue($userA->can('view', $instance));
        $this->assertFalse($userB->can('view', $instance));
    }
}
