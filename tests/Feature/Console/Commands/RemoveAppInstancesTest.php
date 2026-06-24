<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\RemoveAppInstances;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RemoveAppInstancesTest extends TestCase
{
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    #[\Override]
    protected function tearDown(): void
    {
        PolydockAppInstance::flushEventListeners();
        parent::tearDown();
    }

    private function createStoreApp(int $id = 999, string $name = 'Test Store App'): PolydockStoreApp
    {
        $store = PolydockStore::factory()->create();

        return PolydockStoreApp::factory()->create([
            'id' => $id,
            'polydock_store_id' => $store->id,
            'name' => $name,
        ]);
    }

    private function createInstance(
        PolydockStoreApp $storeApp,
        PolydockAppInstanceStatus $status,
        string $name = 'test-instance',
        ?string $email = null
    ): PolydockAppInstance {
        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-'.uniqid();
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = $name;
        $instance->status = $status;
        $instance->app_type = 'test_app_type';
        $instance->data = $email ? ['user-email' => $email] : [];
        $instance->saveQuietly();

        return $instance;
    }

    public function test_fails_when_no_filters_specified(): void
    {
        $this->artisan('polydock:remove-instances')
            ->expectsOutputToContain('You must specify at least one filter option: --app, --email, --name, or --uuid.')
            ->assertFailed();
    }

    public function test_fails_when_app_is_invalid_uuid(): void
    {
        $this->artisan('polydock:remove-instances', ['--app' => '12345'])
            ->expectsOutputToContain('The --app option must be a valid PolydockStoreApp UUID.')
            ->assertFailed();
    }

    public function test_fails_when_store_app_does_not_exist(): void
    {
        $nonExistentUuid = '00000000-0000-0000-0000-000000000000';

        $this->artisan('polydock:remove-instances', ['--app' => $nonExistentUuid])
            ->expectsOutputToContain("No PolydockStoreApp found with UUID: {$nonExistentUuid}")
            ->assertFailed();
    }

    public function test_gracefully_exits_when_no_active_instances_found(): void
    {
        $storeApp = $this->createStoreApp();

        // Create an instance that is already removed (should be ignored)
        $this->createInstance($storeApp, PolydockAppInstanceStatus::REMOVED);

        $this->artisan('polydock:remove-instances', ['--app' => $storeApp->uuid])
            ->expectsOutputToContain('No active app instances found matching the specified filters.')
            ->assertSuccessful();
    }

    public function test_requires_confirmation_and_aborts_on_no(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED);

        $this->artisan('polydock:remove-instances', ['--app' => $storeApp->uuid])
            ->expectsConfirmation('Are you sure you want to set these 1 active app instance(s) to PENDING_PRE_REMOVE status?', 'no')
            ->expectsOutputToContain('Operation cancelled.')
            ->assertSuccessful();

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $instance->status);
    }

    public function test_removes_instances_by_app_uuid(): void
    {
        $storeApp = $this->createStoreApp();
        $instance1 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'inst-1');
        $instance2 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, 'inst-2');

        $this->artisan('polydock:remove-instances', [
            '--app' => $storeApp->uuid,
            '--force' => true,
        ])
            ->expectsOutputToContain('✓ Instance '.$instance1->id.' (inst-1) set to PENDING_PRE_REMOVE')
            ->expectsOutputToContain('✓ Instance '.$instance2->id.' (inst-2) set to PENDING_PRE_REMOVE')
            ->expectsOutputToContain('Operation completed successfully.')
            ->assertSuccessful();

        $instance1->refresh();
        $instance2->refresh();

        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance1->status);
        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance2->status);
    }

    public function test_removes_instances_by_email(): void
    {
        $storeApp = $this->createStoreApp();
        $instance1 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'inst-1', 'test@example.com');
        $instance2 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'inst-2', 'other@example.com');

        $this->artisan('polydock:remove-instances', [
            '--email' => 'test@example.com',
            '--force' => true,
        ])
            ->expectsOutputToContain('✓ Instance '.$instance1->id.' (inst-1) set to PENDING_PRE_REMOVE')
            ->assertSuccessful();

        $instance1->refresh();
        $instance2->refresh();

        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance1->status);
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $instance2->status);
    }

    public function test_removes_instances_by_email_pattern(): void
    {
        $storeApp = $this->createStoreApp();
        $instance1 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'inst-1', 'spam-1@example.com');
        $instance2 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'inst-2', 'spam-2@example.com');
        $instance3 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'inst-3', 'good@domain.com');

        $this->artisan('polydock:remove-instances', [
            '--email' => '%@example.com',
            '--force' => true,
        ])
            ->expectsOutputToContain('✓ Instance '.$instance1->id.' (inst-1) set to PENDING_PRE_REMOVE')
            ->expectsOutputToContain('✓ Instance '.$instance2->id.' (inst-2) set to PENDING_PRE_REMOVE')
            ->assertSuccessful();

        $instance1->refresh();
        $instance2->refresh();
        $instance3->refresh();

        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance1->status);
        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance2->status);
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $instance3->status);
    }

    public function test_removes_instances_by_name(): void
    {
        $storeApp = $this->createStoreApp();
        $instance1 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'unique-name-123');
        $instance2 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'other-name-456');

        $this->artisan('polydock:remove-instances', [
            '--name' => 'unique-name-123',
            '--force' => true,
        ])
            ->expectsOutputToContain('✓ Instance '.$instance1->id.' (unique-name-123) set to PENDING_PRE_REMOVE')
            ->assertSuccessful();

        $instance1->refresh();
        $instance2->refresh();

        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance1->status);
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $instance2->status);
    }

    public function test_removes_instances_by_uuid(): void
    {
        $storeApp = $this->createStoreApp();
        $instance1 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'inst-1');
        $instance2 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'inst-2');

        $this->artisan('polydock:remove-instances', [
            '--uuid' => $instance1->uuid,
            '--force' => true,
        ])
            ->expectsOutputToContain('✓ Instance '.$instance1->id.' (inst-1) set to PENDING_PRE_REMOVE')
            ->assertSuccessful();

        $instance1->refresh();
        $instance2->refresh();

        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance1->status);
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $instance2->status);
    }

    public function test_dry_run_option_does_not_mutate(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE, 'inst-1');

        $this->artisan('polydock:remove-instances', [
            '--app' => $storeApp->uuid,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('DRY RUN: These instances would be set to PENDING_PRE_REMOVE status.')
            ->assertSuccessful();

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE, $instance->status);
    }

    public function test_sets_force_purge_requested_at_when_force_purge_option_is_used(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'inst-1');

        $this->artisan('polydock:remove-instances', [
            '--app' => $storeApp->uuid,
            '--force' => true,
            '--force-purge' => true,
        ])
            ->expectsOutputToContain('✓ Instance '.$instance->id.' (inst-1) set to PENDING_PRE_REMOVE (was: Running healthy (claimed)) (immediate purge requested)')
            ->assertSuccessful();

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance->status);
        $this->assertNotNull($instance->force_purge_requested_at);
        $this->assertNotNull($instance->purge_eligible_at);
    }

    public function test_partial_failure_output_and_exit_code(): void
    {
        $storeApp = $this->createStoreApp();
        $instance1 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'success-inst');
        $instance2 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'fail-inst');

        // Dynamically throw exception on saving for fail-inst
        PolydockAppInstance::saving(function ($instance) {
            if ($instance->name === 'fail-inst') {
                throw new \Exception('Simulated save error');
            }
        });

        $this->artisan('polydock:remove-instances', [
            '--app' => $storeApp->uuid,
            '--force' => true,
        ])
            ->expectsOutputToContain('✓ Instance '.$instance1->id.' (success-inst) set to PENDING_PRE_REMOVE')
            ->expectsOutputToContain('✗ Failed to update instance '.$instance2->id.': Simulated save error')
            ->expectsOutputToContain('Operation completed with errors (partial failure).')
            ->expectsOutputToContain('- Successfully updated: 1')
            ->expectsOutputToContain('- Failed to update: 1')
            ->assertFailed();
    }

    public function test_complete_failure_output_and_exit_code(): void
    {
        $storeApp = $this->createStoreApp();
        $instance1 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'fail-inst-1');

        PolydockAppInstance::saving(function ($instance) {
            throw new \Exception('Simulated complete save error');
        });

        $this->artisan('polydock:remove-instances', [
            '--app' => $storeApp->uuid,
            '--force' => true,
        ])
            ->expectsOutputToContain('✗ Failed to update instance '.$instance1->id.': Simulated complete save error')
            ->expectsOutputToContain('Operation failed.')
            ->expectsOutputToContain('- Successfully updated: 0')
            ->expectsOutputToContain('- Failed to update: 1')
            ->assertFailed();
    }

    public function test_removes_instances_with_limit(): void
    {
        $storeApp = $this->createStoreApp();
        $instance1 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'inst-1');
        $instance2 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'inst-2');
        $instance3 = $this->createInstance($storeApp, PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, 'inst-3');

        // Remove with --limit 2, oldest/lowest ID first (id asc)
        $this->artisan('polydock:remove-instances', [
            '--app' => $storeApp->uuid,
            '--limit' => 2,
            '--force' => true,
        ])
            ->expectsOutputToContain('✓ Instance '.$instance1->id.' (inst-1) set to PENDING_PRE_REMOVE')
            ->expectsOutputToContain('✓ Instance '.$instance2->id.' (inst-2) set to PENDING_PRE_REMOVE')
            ->assertSuccessful();

        $instance1->refresh();
        $instance2->refresh();
        $instance3->refresh();

        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance1->status);
        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance2->status);
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED, $instance3->status);
    }

    public function test_fails_with_invalid_limit(): void
    {
        $storeApp = $this->createStoreApp();

        $this->artisan('polydock:remove-instances', [
            '--app' => $storeApp->uuid,
            '--limit' => 'invalid-limit',
        ])
            ->expectsOutputToContain('The --limit option must be a positive integer.')
            ->assertFailed();

        $this->artisan('polydock:remove-instances', [
            '--app' => $storeApp->uuid,
            '--limit' => 0,
        ])
            ->expectsOutputToContain('The --limit option must be a positive integer.')
            ->assertFailed();

        $this->artisan('polydock:remove-instances', [
            '--app' => $storeApp->uuid,
            '--limit' => -5,
        ])
            ->expectsOutputToContain('The --limit option must be a positive integer.')
            ->assertFailed();

        $this->artisan('polydock:remove-instances', [
            '--app' => $storeApp->uuid,
            '--limit' => '1.5',
        ])
            ->expectsOutputToContain('The --limit option must be a positive integer.')
            ->assertFailed();
    }

    public function test_sensitive_inputs_redacts_email(): void
    {
        $command = new RemoveAppInstances;
        $this->assertContains('email', $command->sensitiveInputs());
    }
}
