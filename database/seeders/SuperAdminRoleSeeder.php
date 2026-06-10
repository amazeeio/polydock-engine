<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class SuperAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $accessAdminPanel = Permission::findOrCreate('access_admin_panel', $guard);

        /** @var Role $superAdmin */
        $superAdmin = Role::findOrCreate('super_admin', $guard);

        if ($superAdmin->label !== 'Super Admin') {
            $superAdmin->label = 'Super Admin';
            $superAdmin->saveQuietly();
        }

        $superAdmin->givePermissionTo($accessAdminPanel);
    }
}
