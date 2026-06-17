<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\UserGroupRoleEnum;
use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Models\PolydockAppInstance;
use App\Models\PolydockBannedPattern;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\UserRemoteRegistration;
use App\Services\EmailBlockerService;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BanEmailsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function createStoreApp(): PolydockStoreApp
    {
        $store = PolydockStore::factory()->create();

        return PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
    }

    private function createAppInstance(
        PolydockStoreApp $storeApp,
        UserGroup $userGroup,
        string $email,
        PolydockAppInstanceStatus $status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED
    ): PolydockAppInstance {
        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-'.uniqid();
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->user_group_id = $userGroup->id;
        $instance->name = 'test-instance-'.uniqid();
        $instance->status = $status;
        $instance->app_type = 'test_app_type';
        $instance->data = ['user-email' => $email];
        $instance->saveQuietly();

        return $instance;
    }

    public function test_normalization_of_input_patterns(): void
    {
        $this->artisan('polydock:ban', [
            'patterns' => ['spammer@gmail.com', '@spam.com', 'spam.ru'],
            '--dry-run' => true,
        ])
            ->expectsOutput('Normalized patterns to ban:')
            ->expectsOutput('  - spammer@gmail.com')
            ->expectsOutput('  - *@spam.com')
            ->expectsOutput('  - *@*.spam.com')
            ->expectsOutput('  - *@spam.ru')
            ->expectsOutput('  - *@*.spam.ru')
            ->assertSuccessful();
    }

    public function test_dry_run_does_not_mutate_database(): void
    {
        $storeApp = $this->createStoreApp();

        $user = User::factory()->create(['email' => 'spammer@spam.com']);
        $group = UserGroup::factory()->create();
        $user->groups()->attach($group, ['role' => UserGroupRoleEnum::OWNER->value]);

        $registration = UserRemoteRegistration::create([
            'email' => 'spammer@spam.com',
            'status' => UserRemoteRegistrationStatusEnum::PENDING,
            'request_data' => [],
        ]);

        $instance = $this->createAppInstance($storeApp, $group, 'spammer@spam.com');

        $this->artisan('polydock:ban', [
            'patterns' => ['@spam.com'],
            '--dry-run' => true,
        ])
            ->assertSuccessful();

        // Database should be unchanged
        $this->assertDatabaseMissing('polydock_banned_patterns', [
            'pattern' => '*@spam.com',
        ]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('user_groups', ['id' => $group->id]);

        $registration->refresh();
        $this->assertEquals(UserRemoteRegistrationStatusEnum::PENDING, $registration->status);

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $instance->status);
    }

    public function test_exact_email_ban_cleanup(): void
    {
        $storeApp = $this->createStoreApp();

        $user = User::factory()->create(['email' => 'spammer@gmail.com']);
        $group = UserGroup::factory()->create();
        $user->groups()->attach($group, ['role' => UserGroupRoleEnum::OWNER->value]);

        $registration = UserRemoteRegistration::create([
            'email' => 'spammer@gmail.com',
            'status' => UserRemoteRegistrationStatusEnum::PENDING,
            'request_data' => [],
        ]);

        $instance = $this->createAppInstance($storeApp, $group, 'spammer@gmail.com');

        $this->artisan('polydock:ban', [
            'patterns' => ['spammer@gmail.com'],
            '--force' => true,
        ])
            ->assertSuccessful();

        // Ban pattern is registered
        $this->assertDatabaseHas('polydock_banned_patterns', [
            'pattern' => 'spammer@gmail.com',
        ]);

        // User deleted
        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        // Group was only occupied by the deleted user, so it must be deleted
        $this->assertDatabaseMissing('user_groups', ['id' => $group->id]);

        // Registration failed
        $registration->refresh();
        $this->assertEquals(UserRemoteRegistrationStatusEnum::FAILED, $registration->status);

        // App instance marked for removal with force purge
        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance->status);
        $this->assertNotNull($instance->force_purge_requested_at);
    }

    public function test_domain_level_ban_cleanup_with_wildcards(): void
    {
        $storeApp = $this->createStoreApp();

        $user1 = User::factory()->create(['email' => 'spammer1@spam.com']);
        $user2 = User::factory()->create(['email' => 'spammer2@sub.spam.com']);
        $safeUser = User::factory()->create(['email' => 'safe@gmail.com']);

        $group1 = UserGroup::factory()->create();
        $user1->groups()->attach($group1, ['role' => UserGroupRoleEnum::OWNER->value]);

        $group2 = UserGroup::factory()->create();
        $user2->groups()->attach($group2, ['role' => UserGroupRoleEnum::OWNER->value]);
        $safeUser->groups()->attach($group2, ['role' => UserGroupRoleEnum::MEMBER->value]);

        $registration1 = UserRemoteRegistration::create([
            'email' => 'any@spam.com',
            'status' => UserRemoteRegistrationStatusEnum::PENDING,
            'request_data' => [],
        ]);

        $registration2 = UserRemoteRegistration::create([
            'email' => 'any@sub.spam.com',
            'status' => UserRemoteRegistrationStatusEnum::PENDING,
            'request_data' => [],
        ]);

        $instance1 = $this->createAppInstance($storeApp, $group1, 'spammer1@spam.com');
        $instance2 = $this->createAppInstance($storeApp, $group2, 'spammer2@sub.spam.com');

        $this->artisan('polydock:ban', [
            'patterns' => ['@spam.com'],
            '--force' => true,
        ])
            ->assertSuccessful();

        // 1. Both patterns added to database
        $this->assertDatabaseHas('polydock_banned_patterns', [
            'pattern' => '*@spam.com',
        ]);
        $this->assertDatabaseHas('polydock_banned_patterns', [
            'pattern' => '*@*.spam.com',
        ]);

        // 2. Both spammer users deleted, safe user stays
        $this->assertDatabaseMissing('users', ['id' => $user1->id]);
        $this->assertDatabaseMissing('users', ['id' => $user2->id]);
        $this->assertDatabaseHas('users', ['id' => $safeUser->id]);

        // 3. Group1 should be deleted (empty), Group2 should stay because safeUser is still a member
        $this->assertDatabaseMissing('user_groups', ['id' => $group1->id]);
        $this->assertDatabaseHas('user_groups', ['id' => $group2->id]);

        // 4. Both registrations failed
        $registration1->refresh();
        $registration2->refresh();
        $this->assertEquals(UserRemoteRegistrationStatusEnum::FAILED, $registration1->status);
        $this->assertEquals(UserRemoteRegistrationStatusEnum::FAILED, $registration2->status);

        // 5. Both app instances set to pending removal and force purge
        $instance1->refresh();
        $instance2->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance1->status);
        $this->assertNotNull($instance1->force_purge_requested_at);
        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance2->status);
        $this->assertNotNull($instance2->force_purge_requested_at);
    }

    public function test_email_blocker_service_identifies_banned_patterns(): void
    {
        PolydockBannedPattern::create([
            'pattern' => '*@spam.com',
            'reason' => 'Domain ban',
        ]);
        PolydockBannedPattern::create([
            'pattern' => '*@*.spam.ru',
            'reason' => 'Subdomain Russian ban',
        ]);
        PolydockBannedPattern::create([
            'pattern' => 'spammer@gmail.com',
            'reason' => 'Exact user',
        ]);

        $service = app(EmailBlockerService::class);

        // Banned exact email
        $res = $service->checkEmail('spammer@gmail.com');
        $this->assertTrue($res->isBlocked());
        $this->assertEquals('Exact user', $res->getReason());

        // Safe email
        $res = $service->checkEmail('safe@gmail.com');
        $this->assertFalse($res->isBlocked());

        // Banned domain
        $res = $service->checkEmail('anything@spam.com');
        $this->assertTrue($res->isBlocked());
        $this->assertEquals('Domain ban', $res->getReason());

        // Safe ending but different domain (avoid false positive)
        $res = $service->checkEmail('anything@notspam.com');
        $this->assertFalse($res->isBlocked());

        // Subdomain of banned Russian domain
        $res = $service->checkEmail('user@sub.spam.ru');
        $this->assertTrue($res->isBlocked());
        $this->assertEquals('Subdomain Russian ban', $res->getReason());

        // Root of Russian domain (which was only banned at subdomain level)
        $res = $service->checkEmail('user@spam.ru');
        $this->assertFalse($res->isBlocked());
    }

    public function test_registration_endpoint_blocks_banned_emails(): void
    {
        PolydockBannedPattern::create([
            'pattern' => '*@spam.com',
            'reason' => 'Domain ban',
        ]);

        $response = $this->postJson('/api/register', [
            'email' => 'user@spam.com',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'failed',
                'message' => 'The email address has been blocked.',
            ]);
    }

    public function test_authenticated_api_create_instance_blocks_banned_emails(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['instances.write']);

        PolydockBannedPattern::create([
            'pattern' => '*@spam.com',
            'reason' => 'Domain ban',
        ]);

        $response = $this->postJson('/api/instance', [
            'email' => 'user@spam.com',
            'storeAppId' => 'some-uuid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'email' => 'The email address has been blocked: Domain ban.',
            ]);
    }

    public function test_underscore_in_ban_pattern_does_not_block_adjacent_characters(): void
    {
        PolydockBannedPattern::create([
            'pattern' => 'spammer_name@gmail.com',
            'reason' => 'Exact underscore user',
        ]);

        $service = app(EmailBlockerService::class);

        // Exactly matches the underscore pattern - should be blocked
        $this->assertTrue($service->checkEmail('spammer_name@gmail.com')->isBlocked());

        // Differs by character in underscore position - should not be blocked
        $this->assertFalse($service->checkEmail('spammer-name@gmail.com')->isBlocked());
        $this->assertFalse($service->checkEmail('spammer1name@gmail.com')->isBlocked());
    }

    public function test_command_does_not_clean_up_adjacent_emails_with_underscores(): void
    {
        $targetUser = User::factory()->create(['email' => 'spammer_name@gmail.com']);
        $safeUser = User::factory()->create(['email' => 'spammer-name@gmail.com']);

        $group1 = UserGroup::factory()->create();
        $targetUser->groups()->attach($group1, ['role' => UserGroupRoleEnum::OWNER->value]);

        $group2 = UserGroup::factory()->create();
        $safeUser->groups()->attach($group2, ['role' => UserGroupRoleEnum::OWNER->value]);

        $this->artisan('polydock:ban', [
            'patterns' => ['spammer_name@gmail.com'],
            '--force' => true,
        ])
            ->assertSuccessful();

        // Target user deleted, safe user remains
        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
        $this->assertDatabaseHas('users', ['id' => $safeUser->id]);

        // Empty group 1 deleted, group 2 remains
        $this->assertDatabaseMissing('user_groups', ['id' => $group1->id]);
        $this->assertDatabaseHas('user_groups', ['id' => $group2->id]);
    }

    public function test_disposable_email_domain_blocks_registration_successfully(): void
    {
        $path = storage_path('app/disposable_domains.json');
        $oldContent = file_exists($path) ? file_get_contents($path) : null;

        // Write a test JSON block with our mock disposable domains
        $testDomains = ['disposable-junk.com', 'test-spam-domain.org'];
        file_put_contents($path, json_encode($testDomains));

        try {
            $service = app(EmailBlockerService::class);

            // Directly check domain blocking
            $this->assertTrue($service->checkEmail('user@disposable-junk.com')->isBlocked());
            $this->assertTrue($service->checkEmail('another-user@sub.test-spam-domain.org')->isBlocked());
            $this->assertFalse($service->checkEmail('user@legit-domain.com')->isBlocked());
        } finally {
            // Restore any previous content
            if ($oldContent !== null) {
                file_put_contents($path, $oldContent);
            } else {
                @unlink($path);
            }
        }
    }
}
