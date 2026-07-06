<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Filament\Admin\RelationManagers\ActivitiesRelationManager;
use App\Filament\Admin\Resources\ActivityLogResource;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuditLogAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_audit_log_list(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('super_admin', config('auth.defaults.guard'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole('super_admin');

        $user->saveAppAuthenticationSecret('test-secret');

        activity()->log('Test entry');

        $this->actingAs($user)
            ->get('/admin/activity-logs')
            ->assertOk();
    }

    public function test_super_admin_can_view_audit_log_detail(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('super_admin', config('auth.defaults.guard'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole('super_admin');

        $user->saveAppAuthenticationSecret('test-secret');

        activity()->log('Detail test');
        $activity = Activity::first();

        $this->actingAs($user)
            ->get("/admin/activity-logs/{$activity->id}")
            ->assertOk();
    }

    public function test_regular_user_cannot_access_audit_log(): void
    {
        $user = User::factory()->create();

        activity()->log('Forbidden entry');

        $this->actingAs($user)
            ->get('/admin/activity-logs')
            ->assertForbidden();
    }

    public function test_activity_log_resource_is_read_only(): void
    {
        $this->assertFalse(ActivityLogResource::canCreate());
    }

    public function test_activities_relation_manager_hidden_for_regular_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $group = UserGroup::factory()->create();

        $this->assertFalse(
            ActivitiesRelationManager::canViewForRecord($group, 'any'),
        );
    }

    public function test_activities_relation_manager_visible_for_super_admin(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('super_admin', config('auth.defaults.guard'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole('super_admin');

        $this->actingAs($user);

        $group = UserGroup::factory()->create();

        $this->assertTrue(
            ActivitiesRelationManager::canViewForRecord($group, 'any'),
        );
    }
}
