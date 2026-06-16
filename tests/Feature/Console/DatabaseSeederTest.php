<?php

namespace Tests\Feature\Console;

use App\Models\PolydockStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that mock data is seeded in non-production environments.
     */
    public function test_it_seeds_mock_data_in_non_production_environments(): void
    {
        // Run seeder in default testing environment
        $this->seed();

        // Fred and team members (Alice, Bob, Carol) should be created
        $this->assertDatabaseHas('users', ['email' => 'fred@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'bob@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'carol@example.com']);

        // Stores should be created
        $this->assertGreaterThan(0, PolydockStore::count());
    }

    /**
     * Test that mock data is NOT seeded in production environment.
     */
    public function test_it_does_not_seed_mock_data_in_production_environment(): void
    {
        // Mock the environment to production
        $this->app->detectEnvironment(fn () => 'production');
        $this->assertEquals('production', app()->environment());

        // Run seeder in production environment with --force to bypass confirmation prompt
        $this->artisan('db:seed', ['--force' => true]);

        // Fred, team members, and stores should NOT be created
        $this->assertEquals(0, User::count());
        $this->assertEquals(0, PolydockStore::count());
    }
}
