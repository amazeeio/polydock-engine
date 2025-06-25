<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralApplicationTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_redirect_for_the_front_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
    }

    /**
     * Test that demonstrates database usage in tests.
     */
    public function test_user_creation_with_database(): void
    {
        // Create a user using the factory
        $user = \App\Models\User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
        ]);

        // Verify the user was created in the database
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        // Verify we can retrieve it
        $retrievedUser = \App\Models\User::where('email', 'test@example.com')->first();
        $this->assertNotNull($retrievedUser);
        $this->assertEquals('Test', $retrievedUser->first_name);
        $this->assertEquals('User', $retrievedUser->last_name);
    }
}
