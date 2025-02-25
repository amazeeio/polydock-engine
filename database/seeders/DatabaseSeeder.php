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

        // Create Fred and his team
        $fred = User::create([
            'name' => 'Fred Blogs',
            'email' => 'fred@example.com',
            'password' => Hash::make('password'),
        ]);

        $fredsTeam = UserGroup::create([
            'name' => "freds-team",
            'friendly_name' => "Fred's Team",
            'slug' => 'freds-team',
        ]);

        // Make Fred the owner
        $fred->groups()->attach($fredsTeam, [
            'role' => UserGroupRoleEnum::OWNER->value
        ]);

        // Create team members with predefined details
        $teamMembers = [
            [
                'name' => 'Alice Smith',
                'email' => 'alice@example.com',
            ],
            [
                'name' => 'Bob Jones',
                'email' => 'bob@example.com',
            ],
            [
                'name' => 'Carol Wilson',
                'email' => 'carol@example.com',
            ],
        ];

        // Create and attach team members
        foreach ($teamMembers as $member) {
            $user = User::create([
                'name' => $member['name'],
                'email' => $member['email'],
                'password' => Hash::make('password'),
            ]);

            $user->groups()->attach($fredsTeam, [
                'role' => UserGroupRoleEnum::MEMBER->value
            ]);
        }
    }
}
