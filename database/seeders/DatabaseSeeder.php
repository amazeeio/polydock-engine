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

        // Create additional team members
        $alice = User::create([
            'name' => 'Alice Smith',
            'email' => 'alice@example.com',
            'password' => Hash::make('password'),
        ]);

        $bob = User::create([
            'name' => 'Bob Jones',
            'email' => 'bob@example.com', 
            'password' => Hash::make('password'),
        ]);

        $carol = User::create([
            'name' => 'Carol Wilson',
            'email' => 'carol@example.com',
            'password' => Hash::make('password'),
        ]);

        // Add them as members to Fred's team
        $alice->groups()->attach($fredsTeam, [
            'role' => UserGroupRoleEnum::MEMBER->value
        ]);

        $bob->groups()->attach($fredsTeam, [
            'role' => UserGroupRoleEnum::MEMBER->value
        ]);

        $carol->groups()->attach($fredsTeam, [
            'role' => UserGroupRoleEnum::MEMBER->value
        ]);
    }
}
