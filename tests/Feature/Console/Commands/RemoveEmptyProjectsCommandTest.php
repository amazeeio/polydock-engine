<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use FreedomtechHosting\FtLagoonPhp\Client;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RemoveEmptyProjectsCommandTest extends TestCase
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
        \Mockery::close();
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
        string $name = 'test-project',
        ?array $data = null
    ): PolydockAppInstance {
        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-'.uniqid();
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = $name;
        $instance->status = $status;
        $instance->app_type = 'test_app_type';
        $instance->data = $data ?? ['project_name' => $name];
        $instance->saveQuietly();

        return $instance;
    }

    public function test_gracefully_exits_when_no_instances_in_removed_state(): void
    {
        $this->artisan('polydock:remove-empty-projects')
            ->expectsOutputToContain('Searching for app instances in REMOVED state...')
            ->expectsOutputToContain('No app instances found in REMOVED state.')
            ->assertSuccessful();
    }

    public function test_bypasses_confirmation_with_force_option(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance($storeApp, PolydockAppInstanceStatus::REMOVED, 'test-project');

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('getProjectByName')
            ->with('test-project')
            ->twice() // Once for probe, once during purge attempt
            ->andReturn([
                'projectByName' => [
                    'environments' => [],
                ],
            ]);

        $mock->shouldReceive('deleteProjectByName')
            ->with('test-project')
            ->once()
            ->andReturn(['deleteProjectByName' => 'success']);

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:remove-empty-projects', ['--force' => true])
            ->expectsOutputToContain('Searching for app instances in REMOVED state...')
            ->expectsOutputToContain('Found 1 app instance(s) in REMOVED state.')
            ->expectsOutputToContain("✓ Project 'test-project' has no environments")
            ->expectsOutputToContain('Found 1 empty project(s) ready for cleanup.')
            ->expectsOutputToContain('✓ Purged instance '.$instance->id.' (test-project)')
            ->assertSuccessful();

        $this->assertSoftDeleted('polydock_app_instances', [
            'id' => $instance->id,
        ]);
    }

    public function test_prompts_for_confirmation_and_aborts_on_no(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance($storeApp, PolydockAppInstanceStatus::REMOVED, 'test-project');

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('getProjectByName')
            ->with('test-project')
            ->once()
            ->andReturn([
                'projectByName' => [
                    'environments' => [],
                ],
            ]);

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:remove-empty-projects')
            ->expectsConfirmation('Are you sure you want to remove these 1 empty Lagoon project(s)?', 'no')
            ->expectsOutputToContain('Operation cancelled.')
            ->assertSuccessful();

        $this->assertDatabaseHas('polydock_app_instances', [
            'id' => $instance->id,
            'deleted_at' => null,
        ]);
    }

    public function test_prompts_for_confirmation_and_purges_on_yes(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance($storeApp, PolydockAppInstanceStatus::REMOVED, 'test-project');

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('getProjectByName')
            ->with('test-project')
            ->twice() // Once for probe, once during purge attempt
            ->andReturn([
                'projectByName' => [
                    'environments' => [],
                ],
            ]);

        $mock->shouldReceive('deleteProjectByName')
            ->with('test-project')
            ->once()
            ->andReturn(['deleteProjectByName' => 'success']);

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:remove-empty-projects')
            ->expectsConfirmation('Are you sure you want to remove these 1 empty Lagoon project(s)?', 'yes')
            ->expectsOutputToContain('✓ Purged instance '.$instance->id.' (test-project)')
            ->assertSuccessful();

        $this->assertSoftDeleted('polydock_app_instances', [
            'id' => $instance->id,
        ]);
    }

    public function test_dry_run_does_not_purge(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance($storeApp, PolydockAppInstanceStatus::REMOVED, 'test-project');

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('getProjectByName')
            ->with('test-project')
            ->once()
            ->andReturn([
                'projectByName' => [
                    'environments' => [],
                ],
            ]);

        // deleteProjectByName should never be called in dry-run
        $mock->shouldNotReceive('deleteProjectByName');

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:remove-empty-projects', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN: The projects listed above would be deleted.')
            ->assertSuccessful();

        $this->assertDatabaseHas('polydock_app_instances', [
            'id' => $instance->id,
            'deleted_at' => null,
        ]);
    }

    public function test_skips_purge_if_project_has_active_environments(): void
    {
        $storeApp = $this->createStoreApp();
        $instance = $this->createInstance($storeApp, PolydockAppInstanceStatus::REMOVED, 'test-project');

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('getProjectByName')
            ->with('test-project')
            ->once()
            ->andReturn([
                'projectByName' => [
                    'environments' => [
                        ['name' => 'main', 'deleted' => null],
                    ],
                ],
            ]);

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:remove-empty-projects', ['--force' => true])
            ->expectsOutputToContain("- Project 'test-project' has 1 environment(s)")
            ->expectsOutputToContain('No empty projects to clean up.')
            ->assertSuccessful();

        $this->assertDatabaseHas('polydock_app_instances', [
            'id' => $instance->id,
            'deleted_at' => null,
        ]);
    }
}
