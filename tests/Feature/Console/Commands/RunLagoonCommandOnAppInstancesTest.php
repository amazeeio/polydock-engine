<?php

namespace Tests\Feature\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use FreedomtechHosting\FtLagoonPhp\Client;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RunLagoonCommandOnAppInstancesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Path to the temporary directory holding the Lagoon SSH key for this test.
     */
    protected ?string $lagoonKeyDir = null;

    protected function tearDown(): void
    {
        if ($this->lagoonKeyDir !== null && is_dir($this->lagoonKeyDir)) {
            $this->deleteDirectory($this->lagoonKeyDir);
            $this->lagoonKeyDir = null;
        }

        \Mockery::close();
        parent::tearDown();
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

    public function test_it_runs_serially_by_default()
    {
        $this->lagoonKeyDir = storage_path('framework/testing/lagoon-key-'.uniqid('', true));

        if (! is_dir($this->lagoonKeyDir)) {
            mkdir($this->lagoonKeyDir, 0700, true);
        }

        $lagoonKeyPath = $this->lagoonKeyDir.DIRECTORY_SEPARATOR.'lagoon-private-key';

        config(['polydock.service_providers_singletons.PolydockServiceProviderFTLagoon' => [
            'ssh_server' => 'ssh.lagoon.test',
            'ssh_port' => '2222',
            'ssh_private_key_file' => $lagoonKeyPath,
        ]]);

        $this->app->instance('polydock.lagoon.token_fetcher', fn ($config) => 'fake-token');

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();
        $mock->shouldReceive('executeCommandOnProjectEnvironment')
            ->with('project-1', 'main', 'drush cr', 'cli', 'cli')
            ->once()
            ->andReturn(['data' => 'success']);
        $mock->shouldReceive('executeCommandOnProjectEnvironment')
            ->with('project-2', 'develop', 'drush cr', 'cli', 'cli')
            ->once()
            ->andReturn(['data' => 'success']);
        $this->app->instance(Client::class, $mock);

        // Arrange
        $store = PolydockStore::factory()->create([
            'lagoon_deploy_project_prefix' => 'test-prefix',
        ]);

        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'uuid' => 'test-app-uuid',
            'name' => 'Test App',
            'lagoon_deploy_branch' => 'main',
        ]);

        // Manually create instances since factory might not exist or be complex
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
        $instance1->saveQuietly(); // Skip events

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

        // Act
        $this->artisan('polydock:instances:run-lagoon-command', [
            'app_uuid' => $storeApp->uuid,
            'command_name' => 'drush cr',
            '--force' => true,
        ])
            ->expectsOutput('Found 2 running instances.')
            ->expectsOutput('Authenticating with Lagoon (Serial Mode)...')
            ->assertExitCode(0);
    }

    public function test_it_runs_concurrently()
    {
        // Arrange
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

        // Act
        $this->artisan('polydock:instances:run-lagoon-command', [
            'app_uuid' => $storeApp->uuid,
            'command_name' => 'drush cr',
            '--force' => true,
            '--concurrency' => 2,
        ])
            ->expectsOutput('Running Lagoon commands concurrently on 2 instances (concurrency: 2)...')
            ->assertExitCode(0);

        // Assert
        Process::assertRan(function ($process) use ($instance1) {
            $cmd = $process->command;
            $hasId = is_array($cmd) ? in_array("--instance-id={$instance1->id}", $cmd) : str_contains($cmd, "--instance-id={$instance1->id}");

            return $hasId && (is_array($cmd) ? in_array('drush cr', $cmd) : str_contains($cmd, 'drush cr'));
        });
        Process::assertRan(function ($process) use ($instance2) {
            $cmd = $process->command;
            $hasId = is_array($cmd) ? in_array("--instance-id={$instance2->id}", $cmd) : str_contains($cmd, "--instance-id={$instance2->id}");

            return $hasId && (is_array($cmd) ? in_array('drush cr', $cmd) : str_contains($cmd, 'drush cr'));
        });
    }

    public function test_it_skips_instances_missing_metadata()
    {
        // Arrange
        $store = PolydockStore::factory()->create();

        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'uuid' => 'test-app-uuid',
        ]);

        $instance1 = new PolydockAppInstance;
        $instance1->uuid = 'test-instance-uuid-1';
        $instance1->polydock_store_app_id = $storeApp->id;
        $instance1->name = 'test-instance-broken';
        $instance1->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance1->app_type = 'test_app_type';
        $instance1->data = []; // Missing lagoon-project-name
        $instance1->saveQuietly();

        config(['polydock.service_providers_singletons.PolydockServiceProviderFTLagoon' => [
            'ssh_private_key_file' => base_path('tests/fixtures/lagoon-private-key'),
        ]]);
        if (! file_exists(base_path('tests/fixtures'))) {
            mkdir(base_path('tests/fixtures'), 0777, true);
        }
        file_put_contents(base_path('tests/fixtures/lagoon-private-key'), 'dummy-key');

        $this->app->instance('polydock.lagoon.token_fetcher', fn (array $config) => 'fake-token');

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();
        $mock->shouldNotReceive('executeCommandOnProjectEnvironment');
        $this->app->instance(Client::class, $mock);

        // Act
        $this->artisan('polydock:instances:run-lagoon-command', [
            'app_uuid' => $storeApp->uuid,
            'command_name' => 'drush cr',
            '--force' => true,
        ])
            ->assertExitCode(0);
    }
}
