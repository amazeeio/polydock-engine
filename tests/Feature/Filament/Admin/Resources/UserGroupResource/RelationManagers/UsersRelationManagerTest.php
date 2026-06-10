<?php

namespace Tests\Feature\Filament\Admin\Resources\UserGroupResource\RelationManagers;

use App\Enums\UserGroupRoleEnum;
use App\Filament\Admin\Resources\UserGroupResource\Pages\EditUserGroup;
use App\Filament\Admin\Resources\UserGroupResource\RelationManagers\UsersRelationManager;
use App\Models\User;
use App\Models\UserGroup;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsersRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected UserGroup $userGroup;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super_admin role for policy bypass
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);

        // Create an admin user who can access the panel
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
        ]);
        $this->admin->assignRole('super_admin');

        // Create a user group
        $this->userGroup = UserGroup::factory()->create([
            'name' => 'Test Group',
        ]);

        // Set the admin panel as current for Filament
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_can_attach_existing_user_to_group(): void
    {
        $this->actingAs($this->admin);

        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        Livewire::test(UsersRelationManager::class, [
            'ownerRecord' => $this->userGroup,
            'pageClass' => EditUserGroup::class,
        ])
            ->callTableAction('add_user', data: [
                'user_id' => $existingUser->id,
                'role' => UserGroupRoleEnum::MEMBER->value,
                'mode' => 'existing',
            ])
            ->assertHasNoTableActionErrors();

        // Verify user is attached to the group with the correct role
        $this->assertTrue($this->userGroup->users()->where('users.id', $existingUser->id)->exists());
        $this->assertEquals(
            UserGroupRoleEnum::MEMBER->value,
            $this->userGroup->users()->where('users.id', $existingUser->id)->first()->pivot->role
        );
    }

    public function test_can_create_and_attach_new_user_to_group(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UsersRelationManager::class, [
            'ownerRecord' => $this->userGroup,
            'pageClass' => EditUserGroup::class,
        ])
            ->callTableAction('add_user', data: [
                'mode' => 'new',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'newuser@example.com',
                'password' => 'password123',
                'role' => UserGroupRoleEnum::OWNER->value,
            ])
            ->assertHasNoTableActionErrors();

        // Verify user was created in the database
        $newUser = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals('John', $newUser->first_name);
        $this->assertEquals('Doe', $newUser->last_name);

        // Verify user is attached to the group with the correct role
        $this->assertTrue($this->userGroup->users()->where('users.id', $newUser->id)->exists());
        $this->assertEquals(
            UserGroupRoleEnum::OWNER->value,
            $this->userGroup->users()->where('users.id', $newUser->id)->first()->pivot->role
        );
    }

    public function test_add_user_form_validates_required_fields_when_creating_new(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UsersRelationManager::class, [
            'ownerRecord' => $this->userGroup,
            'pageClass' => EditUserGroup::class,
        ])
            ->callTableAction('add_user', data: [
                'mode' => 'new',
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'password' => '',
                'role' => null,
            ])
            ->assertHasTableActionErrors([
                'first_name' => 'required',
                'last_name' => 'required',
                'email' => 'required',
                'password' => 'required',
                'role' => 'required',
            ]);
    }

    public function test_add_user_form_validates_required_user_id_when_attaching_existing(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(UsersRelationManager::class, [
            'ownerRecord' => $this->userGroup,
            'pageClass' => EditUserGroup::class,
        ])
            ->callTableAction('add_user', data: [
                'mode' => 'existing',
                'user_id' => null,
                'role' => UserGroupRoleEnum::MEMBER->value,
            ])
            ->assertHasTableActionErrors([
                'user_id' => 'required',
            ]);
    }

    public function test_edit_user_role_logs_audit_entry_when_changed(): void
    {
        $this->actingAs($this->admin);
        $member = User::factory()->create();
        $this->userGroup->users()->attach($member->id, ['role' => UserGroupRoleEnum::MEMBER->value]);

        Activity::query()->delete();

        Livewire::test(UsersRelationManager::class, [
            'ownerRecord' => $this->userGroup,
            'pageClass' => EditUserGroup::class,
        ])
            ->callTableAction('edit', $member, data: [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'email' => $member->email,
                'role' => UserGroupRoleEnum::ADMIN->value,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('activity_log', [
            'description' => "User '{$member->email}' role changed from 'member' to 'admin'",
            'subject_id' => $this->userGroup->id,
            'subject_type' => UserGroup::class,
        ]);
    }

    public function test_edit_user_does_not_log_role_change_audit_entry_when_role_unchanged(): void
    {
        $this->actingAs($this->admin);
        $member = User::factory()->create();
        $this->userGroup->users()->attach($member->id, ['role' => UserGroupRoleEnum::MEMBER->value]);

        Activity::query()->delete();

        Livewire::test(UsersRelationManager::class, [
            'ownerRecord' => $this->userGroup,
            'pageClass' => EditUserGroup::class,
        ])
            ->callTableAction('edit', $member, data: [
                'first_name' => 'Updated Name',
                'last_name' => $member->last_name,
                'email' => $member->email,
                'role' => UserGroupRoleEnum::MEMBER->value,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('activity_log', [
            'description' => "User '{$member->email}' role changed from 'member' to 'member'",
        ]);

        $this->assertEquals(0, Activity::where('description', 'like', '%role changed%')->count());
    }
}
