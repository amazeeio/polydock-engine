<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class ServiceAccountRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        /** @var Role $role */
        $role = Role::findOrCreate('service-account', $guard);

        if ($role->label !== 'Service Account') {
            $role->label = 'Service Account';
            $role->saveQuietly();
        }

        // Seed Horizon permissions
        $viewHorizon = Permission::findOrCreate('view_horizon', $guard);
        Permission::findOrCreate('mutate_horizon', $guard);

        // Grant read-only view_horizon permission to service-account
        $role->givePermissionTo($viewHorizon);
    }
}
