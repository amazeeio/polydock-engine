<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Enums\UserGroupRoleEnum;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\GroupMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class GroupMembershipAuditTest extends TestCase
{
    use RefreshDatabase;

    private GroupMembershipService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GroupMembershipService::class);
    }

    public function test_add_user_to_group_logs_activity(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::create(['name' => 'Membership Audit Group']);
        $actor = User::factory()->create();

        Activity::query()->delete();

        $this->service->addUserToGroup($user, $group, UserGroupRoleEnum::MEMBER, $actor);

        $activity = Activity::where('description', 'like', "%{$user->email}%added%")->first();

        $this->assertNotNull($activity);
        $this->assertEquals($group->id, $activity->subject_id);
        $this->assertEquals(UserGroup::class, $activity->subject_type);
        $this->assertEquals($actor->id, $activity->causer_id);
        $this->assertEquals('group.member_added', $activity->properties['action']);
        $this->assertEquals($user->email, $activity->properties['user_email']);
        $this->assertEquals(UserGroupRoleEnum::MEMBER->value, $activity->properties['role']);

        // Verify the user was actually attached
        $this->assertTrue($group->users()->whereKey($user->id)->exists());
    }

    public function test_remove_user_from_group_logs_activity(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::create(['name' => 'Remove Audit Group']);
        $actor = User::factory()->create();

        $group->users()->attach($user->id, ['role' => UserGroupRoleEnum::ADMIN->value]);

        Activity::query()->delete();

        $this->service->removeUserFromGroup($user, $group, $actor);

        $activity = Activity::where('description', 'like', "%{$user->email}%removed%")->first();

        $this->assertNotNull($activity);
        $this->assertEquals($group->id, $activity->subject_id);
        $this->assertEquals($actor->id, $activity->causer_id);
        $this->assertEquals('group.member_removed', $activity->properties['action']);
        $this->assertEquals(UserGroupRoleEnum::ADMIN->value, $activity->properties['previous_role']);

        // Verify the user was actually detached
        $this->assertFalse($group->users()->whereKey($user->id)->exists());
    }

    public function test_change_user_role_logs_activity(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::create(['name' => 'Role Change Audit Group']);
        $actor = User::factory()->create();

        $group->users()->attach($user->id, ['role' => UserGroupRoleEnum::MEMBER->value]);

        Activity::query()->delete();

        $this->service->changeUserRole($user, $group, UserGroupRoleEnum::ADMIN, $actor);

        $activity = Activity::where('description', 'like', '%role changed%')->first();

        $this->assertNotNull($activity);
        $this->assertEquals('group.member_role_changed', $activity->properties['action']);
        $this->assertEquals(UserGroupRoleEnum::MEMBER->value, $activity->properties['previous_role']);
        $this->assertEquals(UserGroupRoleEnum::ADMIN->value, $activity->properties['new_role']);

        // Verify the role was actually changed
        $pivot = $group->users()->whereKey($user->id)->first()->pivot;
        $this->assertEquals(UserGroupRoleEnum::ADMIN->value, $pivot->getAttribute('role'));
    }

    public function test_remove_non_member_does_not_log_activity(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::create(['name' => 'Non-member Remove Group']);
        $actor = User::factory()->create();

        Activity::query()->delete();

        $this->service->removeUserFromGroup($user, $group, $actor);

        $this->assertCount(0, Activity::all());
    }

    public function test_change_role_for_non_member_does_not_log_activity(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::create(['name' => 'Non-member Role Group']);
        $actor = User::factory()->create();

        Activity::query()->delete();

        $this->service->changeUserRole($user, $group, UserGroupRoleEnum::ADMIN, $actor);

        $this->assertCount(0, Activity::all());
    }

    public function test_change_role_to_same_role_does_not_log_activity(): void
    {
        $user = User::factory()->create();
        $group = UserGroup::create(['name' => 'Same Role Group']);
        $actor = User::factory()->create();

        $group->users()->attach($user->id, ['role' => UserGroupRoleEnum::MEMBER->value]);

        Activity::query()->delete();

        $this->service->changeUserRole($user, $group, UserGroupRoleEnum::MEMBER, $actor);

        $this->assertCount(0, Activity::all());
    }
}
