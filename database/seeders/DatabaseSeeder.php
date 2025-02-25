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
        // Create Fred and his team
        $fred = User::create([
            'first_name' => 'Fred',
            'last_name' => 'Blogs',
            'email' => 'fred@example.com',
            'password' => Hash::make('password'),
        ]);

        $fredsTeam = UserGroup::create([
            'name' => "Fracme Inc."
        ]);

        // Make Fred the owner
        $fred->groups()->attach($fredsTeam, [
            'role' => UserGroupRoleEnum::OWNER->value
        ]);

        // Create team members with predefined details
        $teamMembers = [
            [
                'first_name' => 'Alice',
                'last_name' => 'Smith',
                'email' => 'alice@example.com',
            ],
            [
                'first_name' => 'Bob',
                'last_name' => 'Jones',
                'email' => 'bob@example.com',
            ],
            [
                'first_name' => 'Carol',
                'last_name' => 'Wilson',
                'email' => 'carol@example.com',
            ],
        ];

        // Create and attach team members
        foreach ($teamMembers as $member) {
            $user = User::create([
                'first_name' => $member['first_name'],
                'last_name' => $member['last_name'],
                'email' => $member['email'],
                'password' => Hash::make('password'),
            ]);

            $user->groups()->attach($fredsTeam, [
                'role' => UserGroupRoleEnum::MEMBER->value
            ]);
        }

        // Create the stores
        $usaStore = \App\Models\PolydockStore::create([
            'name' => 'USA Store',
            'status' => \App\Enums\PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
        ]);

        $switzerlandStore = \App\Models\PolydockStore::create([
            'name' => 'Switzerland Store',
            'status' => \App\Enums\PolydockStoreStatusEnum::PUBLIC,
            'listed_in_marketplace' => true,
        ]);

        // Add some example apps to each store
        \App\Models\PolydockStoreApp::factory()
            ->count(8)
            ->sequence(
                ['polydock_store_id' => $usaStore->id],
                ['polydock_store_id' => $switzerlandStore->id],
            )
            ->create();
    }
}
