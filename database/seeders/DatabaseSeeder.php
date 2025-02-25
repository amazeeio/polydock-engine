<?php

namespace Database\Seeders;

use App\Enums\UserGroupRoleEnum;
use App\Models\User;
use App\Models\UserGroup;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Create Fred
        $fred = User::create([
            'name' => 'Fred Blogs',
            'email' => 'fred@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create Fred's Team
        $fredsTeam = UserGroup::create([
            'name' => "freds-team",
            'friendly_name' => "Fred's Team",
            'slug' => 'freds-team',
        ]);

        // Attach Fred as the owner of his team
        $fred->groups()->attach($fredsTeam, [
            'role' => UserGroupRoleEnum::OWNER->value
        ]);

        // Create a random user
        $user = User::factory()->create();
    }
}
