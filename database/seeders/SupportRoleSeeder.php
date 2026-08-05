<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class SupportRoleSeeder extends Seeder
{
    /**
     * Support staff can create and manage customer app instances and their
     * users, and view activity logs. No store/app management, system
     * settings or billing.
     */
    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        /** @var Role $role */
        $role = Role::findOrCreate('support', $guard);

        if ($role->label !== 'Support') {
            $role->label = 'Support';
            $role->saveQuietly();
        }

        $permissions = [
            'access_admin_panel',
            'view_polydock_app_instance',
            'create_polydock_app_instance',
            'update_polydock_app_instance',
            'view_any_user',
            'view_user',
            'create_user',
            'update_user',
            'view_user_group',
            'view_any_activity_log',
            'view_activity_log',
        ];

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, $guard));
        }
    }
}
