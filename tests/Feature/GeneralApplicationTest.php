<?php

namespace Tests\Feature;

use Amazeeio\PolydockAppAmazeeclaw\PolydockAmazeeClawAiApp;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Models\UserGroup;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
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
        $user = User::factory()->create([
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
        $retrievedUser = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($retrievedUser);
        $this->assertEquals('Test', $retrievedUser->first_name);
        $this->assertEquals('User', $retrievedUser->last_name);
    }

    /**
     * Test that long status messages are safely truncated by the model mutator.
     */
    public function test_status_message_is_safely_truncated(): void
    {
        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
        $userGroup = UserGroup::factory()->create();

        $instance = new PolydockAppInstance;
        $instance->fill([
            'polydock_store_app_id' => $storeApp->id,
            'user_group_id' => $userGroup->id,
            'name' => 'test-truncation-instance',
            'app_type' => PolydockAmazeeClawAiApp::class,
            'status' => PolydockAppInstanceStatus::NEW,
        ]);

        // Create a massive status message (70,000 characters)
        $longMessage = str_repeat('A', 70000);
        $instance->status_message = $longMessage;
        $instance->saveQuietly();

        // Refresh and assert truncation to 65000 chars plus trailing '...'
        $instance->refresh();
        $this->assertEquals(65000, strlen($instance->status_message));
        $this->assertTrue(str_ends_with($instance->status_message, '...'));
    }
}
