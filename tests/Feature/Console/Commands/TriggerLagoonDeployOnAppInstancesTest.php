<?php

namespace Tests\Feature\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Clients\Lagoon\Client;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class TriggerLagoonDeployOnAppInstancesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Path to the temporary directory holding the Lagoon SSH key for this test.
     */
    protected ?string $lagoonKeyDir = null;

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if ($this->lagoonKeyDir !== null && is_dir($this->lagoonKeyDir)) {
                $this->deleteDirectory($this->lagoonKeyDir);
                $this->lagoonKeyDir = null;
            }

            \Mockery::close();
        }
    }

    /**
     * Recursively delete a directory and its contents.
     */
    private function deleteDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }

    /**
     * Set up a temporary Lagoon key and bind a fake token fetcher.
     */
    private function setupLagoonKey(): void
    {
        $this->lagoonKeyDir = storage_path('framework/testing/lagoon-key-'.uniqid('', true));

        if (! is_dir($this->lagoonKeyDir)) {
            mkdir($this->lagoonKeyDir, 0700, true);
        }

        $lagoonKeyPath = $this->lagoonKeyDir.DIRECTORY_SEPARATOR.'lagoon-private-key';
        file_put_contents($lagoonKeyPath, 'dummy-key');

        config(['polydock.service_providers_singletons.PolydockServiceProviderFTLagoon' => [
            'ssh_server' => 'ssh.lagoon.test',
            'ssh_port' => '2222',
            'ssh_private_key_file' => $lagoonKeyPath,
        ]]);

        $this->app->instance('polydock.lagoon.token_fetcher', fn (array $config) => 'fake-token');
    }

    public function test_it_runs_serially_by_default(): void
    {
        $this->setupLagoonKey();

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();
        $mock->shouldReceive('bulkDeployEnvironments')
            ->with([
                ['project' => 'project-1', 'name' => 'main'],
                ['project' => 'project-2', 'name' => 'develop'],
            ], \Mockery::type('string'), [])
            ->once()
            ->andReturn(['bulkDeployEnvironmentLatest' => 'bulk-id-123']);
        $this->app->instance(Client::class, $mock);

        $store = PolydockStore::factory()->create([
            'lagoon_deploy_project_prefix' => 'test-prefix',
        ]);

        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'uuid' => 'test-app-uuid',
            'name' => 'Test App',
            'lagoon_deploy_branch' => 'main',
        ]);

        $instance1 = new PolydockAppInstance;
        $instance1->uuid = 'test-instance-uuid-1';
        $instance1->polydock_store_app_id = $storeApp->id;
        $instance1->name = 'test-instance-1';
        $instance1->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance1->app_type = 'test_app_type';
        $instance1->data = [
            'lagoon-project-name' => 'project-1',
            'lagoon-deploy-branch' => 'main',
        ];
        $instance1->saveQuietly();

        $instance2 = new PolydockAppInstance;
        $instance2->uuid = 'test-instance-uuid-2';
        $instance2->polydock_store_app_id = $storeApp->id;
        $instance2->name = 'test-instance-2';
        $instance2->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance2->app_type = 'test_app_type';
        $instance2->data = [
            'lagoon-project-name' => 'project-2',
            'lagoon-deploy-branch' => 'develop',
        ];
        $instance2->saveQuietly();

        Process::fake();

        $this->artisan('polydock:instances:trigger-deploy', [
            'app_uuid' => $storeApp->uuid,
            '--force' => true,
        ])
            ->expectsOutput('Found 2 running instances.')
            ->expectsOutput('Authenticating with Lagoon...')
            ->expectsOutput('Triggering bulk deployment for 2 instances...')
            ->expectsOutput('Bulk deployment triggered successfully! Bulk ID: bulk-id-123')
            ->assertExitCode(0);
    }

    public function test_it_runs_concurrently(): void
    {
        $this->setupLagoonKey();

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();
        $mock->shouldReceive('bulkDeployEnvironments')
            ->with([
                ['project' => 'project-1', 'name' => 'main'],
                ['project' => 'project-2', 'name' => 'main'],
            ], \Mockery::type('string'), [])
            ->once()
            ->andReturn(['bulkDeployEnvironmentLatest' => 'bulk-id-456']);
        $this->app->instance(Client::class, $mock);

        $store = PolydockStore::factory()->create([
            'lagoon_deploy_project_prefix' => 'test-prefix',
        ]);

        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'uuid' => 'test-app-uuid',
            'lagoon_deploy_branch' => 'main',
        ]);

        $instance1 = new PolydockAppInstance;
        $instance1->uuid = 'test-instance-uuid-1';
        $instance1->polydock_store_app_id = $storeApp->id;
        $instance1->name = 'test-instance-1';
        $instance1->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance1->app_type = 'test_app_type';
        $instance1->data = [
            'lagoon-project-name' => 'project-1',
            'lagoon-deploy-branch' => 'main',
        ];
        $instance1->saveQuietly();

        $instance2 = new PolydockAppInstance;
        $instance2->uuid = 'test-instance-uuid-2';
        $instance2->polydock_store_app_id = $storeApp->id;
        $instance2->name = 'test-instance-2';
        $instance2->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance2->app_type = 'test_app_type';
        $instance2->data = [
            'lagoon-project-name' => 'project-2',
            'lagoon-deploy-branch' => 'main',
        ];
        $instance2->saveQuietly();

        Process::fake();

        $this->artisan('polydock:instances:trigger-deploy', [
            'app_uuid' => $storeApp->uuid,
            '--force' => true,
            '--concurrency' => 2,
        ])
            ->expectsOutput('Authenticating with Lagoon...')
            ->expectsOutput('Triggering bulk deployment for 2 instances...')
            ->expectsOutput('Bulk deployment triggered successfully! Bulk ID: bulk-id-456')
            ->assertExitCode(0);
    }

    public function test_it_deploys_variables_only(): void
    {
        $this->setupLagoonKey();

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();
        $mock->shouldReceive('bulkDeployEnvironments')
            ->with([
                ['project' => 'project-1', 'name' => 'main'],
            ], \Mockery::type('string'), ['LAGOON_VARIABLES_ONLY' => 'true'])
            ->once()
            ->andReturn(['bulkDeployEnvironmentLatest' => 'bulk-id-789']);
        $this->app->instance(Client::class, $mock);

        $store = PolydockStore::factory()->create();

        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'uuid' => 'test-app-uuid',
        ]);

        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-instance-uuid-1';
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'test-instance-1';
        $instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance->app_type = 'test_app_type';
        $instance->data = [
            'lagoon-project-name' => 'project-1',
            'lagoon-deploy-branch' => 'main',
        ];
        $instance->saveQuietly();

        $this->artisan('polydock:instances:trigger-deploy', [
            'app_uuid' => $storeApp->uuid,
            '--force' => true,
            '--variables-only' => true,
        ])
            ->assertExitCode(0);
    }

    public function test_it_skips_instances_missing_metadata(): void
    {
        $this->setupLagoonKey();

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();
        $mock->shouldNotReceive('bulkDeployEnvironments');
        $this->app->instance(Client::class, $mock);

        $store = PolydockStore::factory()->create();

        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'uuid' => 'test-app-uuid',
        ]);

        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-instance-uuid-1';
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'test-instance-broken';
        $instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance->app_type = 'test_app_type';
        $instance->data = []; // Missing lagoon-project-name and lagoon-deploy-branch
        $instance->saveQuietly();

        $this->artisan('polydock:instances:trigger-deploy', [
            'app_uuid' => $storeApp->uuid,
            '--force' => true,
        ])
            ->expectsOutput('No valid environments found to deploy.')
            ->assertExitCode(1);
    }

    public function test_it_returns_error_when_store_app_not_found(): void
    {
        $this->artisan('polydock:instances:trigger-deploy', [
            'app_uuid' => 'non-existent-uuid',
            '--force' => true,
        ])
            ->expectsOutput('Store App with UUID non-existent-uuid not found.')
            ->assertExitCode(1);
    }

    public function test_it_returns_zero_when_no_running_instances(): void
    {
        $store = PolydockStore::factory()->create();

        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'uuid' => 'test-app-uuid',
        ]);

        $this->artisan('polydock:instances:trigger-deploy', [
            'app_uuid' => $storeApp->uuid,
            '--force' => true,
        ])
            ->expectsOutput('No running instances found for this app.')
            ->assertExitCode(0);
    }
}
