<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\Role;
use App\Models\User;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Covers the policy behaviour that is NOT a plain permission lookup:
 * hardcoded denials, self-access escape hatches and the store-delete guard.
 * The straight `$user->can('...')` wrappers are covered by one table-driven
 * pass rather than a test per method.
 */
class PolicyRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $guard = config('auth.defaults.guard');

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        $user->givePermissionTo($permissions);

        return $user->fresh();
    }

    public function test_activity_log_is_immutable_regardless_of_permissions(): void
    {
        // Audit rows must never be writable through the panel, so these three
        // return a hardcoded false — no permission should unlock them.
        $user = $this->userWith('view_any_activity_log', 'view_activity_log');
        $activity = Activity::create(['description' => 'test', 'log_name' => 'default']);

        $this->assertTrue($user->can('viewAny', Activity::class));
        $this->assertTrue($user->can('view', $activity));

        $this->assertFalse($user->can('create', Activity::class));
        $this->assertFalse($user->can('update', $activity));
        $this->assertFalse($user->can('delete', $activity));
    }

    public function test_activity_log_reads_require_permission(): void
    {
        $user = User::factory()->create();
        $activity = Activity::create(['description' => 'test', 'log_name' => 'default']);

        $this->assertFalse($user->can('viewAny', Activity::class));
        $this->assertFalse($user->can('view', $activity));
    }

    public function test_user_can_always_view_and_update_self(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertTrue($user->can('view', $user));
        $this->assertTrue($user->can('update', $user));

        $this->assertFalse($user->can('view', $other));
        $this->assertFalse($user->can('update', $other));

        // Self-access must not leak into deletion.
        $this->assertFalse($user->can('delete', $user));
    }

    public function test_user_permissions_grant_access_to_others(): void
    {
        $user = $this->userWith('view_any_user', 'view_user', 'create_user', 'update_user', 'delete_user');
        $other = User::factory()->create();

        $this->assertTrue($user->can('viewAny', User::class));
        $this->assertTrue($user->can('view', $other));
        $this->assertTrue($user->can('create', User::class));
        $this->assertTrue($user->can('update', $other));
        $this->assertTrue($user->can('delete', $other));
    }

    public function test_store_cannot_be_deleted_while_an_app_has_instances(): void
    {
        $user = User::factory()->create();

        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create(['polydock_store_id' => $store->id]);

        $this->assertTrue($user->can('delete', $store->fresh()));

        $instance = new PolydockAppInstance;
        $instance->fill([
            'polydock_store_app_id' => $storeApp->id,
            'name' => 'policy-'.Str::random(6),
            'app_type' => 'test-app',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);
        $instance->uuid = (string) Str::uuid();
        $instance->saveQuietly();

        $this->assertFalse($user->can('delete', $store->fresh()));
    }

    public function test_empty_store_is_deletable(): void
    {
        $user = User::factory()->create();
        $store = PolydockStore::factory()->create();

        $this->assertTrue($user->can('delete', $store));
    }

    /**
     * Every RolePolicy method is a bare permission lookup — assert the whole
     * matrix in one pass instead of a test per ability.
     */
    public function test_role_abilities_track_their_permissions(): void
    {
        $abilities = [
            'viewAny' => 'view_any_role',
            'view' => 'view_role',
            'create' => 'create_role',
            'update' => 'update_role',
            'delete' => 'delete_role',
            'deleteAny' => 'delete_any_role',
            'forceDelete' => 'force_delete_role',
            'forceDeleteAny' => 'force_delete_any_role',
            'restore' => 'restore_role',
            'restoreAny' => 'restore_any_role',
            'replicate' => 'replicate_role',
            'reorder' => 'reorder_role',
        ];

        $role = Role::findOrCreate('some_role', config('auth.defaults.guard'));

        $denied = User::factory()->create();
        $allowed = $this->userWith(...array_values($abilities));

        foreach ($abilities as $ability => $permission) {
            $this->assertFalse($denied->can($ability, $role), "{$ability} should be denied without {$permission}");
            $this->assertTrue($allowed->can($ability, $role), "{$ability} should be allowed with {$permission}");
        }
    }
}
