<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class ServiceAccountRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var Role $role */
        $role = Role::findOrCreate('service-account', config('auth.defaults.guard'));

        if ($role->label !== 'Service Account') {
            $role->label = 'Service Account';
            $role->saveQuietly();
        }
    }
}
