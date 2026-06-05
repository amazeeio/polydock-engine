<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SuperAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        $accessAdminPanel = Permission::findOrCreate('access_admin_panel', $guard);

        $superAdmin = Role::findOrCreate('super_admin', $guard);
        $superAdmin->givePermissionTo($accessAdminPanel);
    }
}
