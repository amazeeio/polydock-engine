<?php

namespace Tests\Feature\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class RunLagoonCommandOnAppInstancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable events to avoid complex setup requirements unless needed
        // PolydockAppInstance::unsetEventDispatcher();
        // Actually, the creating event sets vital data like 'data' array. We probably need it or simulate it.
    }

    public function test_it_runs_commands_serially_by_default()
    {
        config(['polydock.service_providers_singletons.PolydockServiceProviderFTLagoon' => [
            'ssh_server' => 'ssh.lagoon.test',
            'ssh_port' => '32222',
        ]]);

        // Arrange
        $store = \App\Models\PolydockStore::factory()->create([
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
        $instance1->polydock_store_app_id = $storeApp->id;
        $instance1->name = 'test-instance-1';
        $instance1->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance1->app_type = 'test_app_type';
        $instance1->data = [
            'lagoon-project-name' => 'project-1',
            'lagoon-deploy-branch' => 'main',
            'lagoon-deploy-private-key' => 'test-key',
        ];
        $instance1->saveQuietly(); // Skip events

        $instance2 = new PolydockAppInstance;
        $instance2->polydock_store_app_id = $storeApp->id;
        $instance2->name = 'test-instance-2';
        $instance2->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance2->app_type = 'test_app_type';
        $instance2->data = [
            'lagoon-project-name' => 'project-2',
            'lagoon-deploy-branch' => 'develop',
            'lagoon-deploy-private-key' => 'test-key',
        ];
        $instance2->saveQuietly();

        Process::fake();

        // Act
        $this->artisan('polydock:instances:run-lagoon-command', [
            'app_uuid' => $storeApp->uuid,
            'cmd' => 'ls -la',
            '--force' => true,
        ])
            ->expectsOutput('Found 2 running instances.')
            ->assertExitCode(0);

        // Assert
        Process::assertRan(function ($process, $result) {
            $cmd = (string) $process->command;

            return str_contains($cmd, 'ssh') &&
                   str_contains($cmd, 'project-1-main@ssh.lagoon.test') &&
                   str_contains($cmd, 'service=cli container=cli ls -la');
        });

        Process::assertRan(function ($process, $result) {
            $cmd = (string) $process->command;

            return str_contains($cmd, 'ssh') &&
                   str_contains($cmd, 'project-2-develop@ssh.lagoon.test') &&
                   str_contains($cmd, 'service=cli container=cli ls -la');
        });
    }

    public function test_it_runs_commands_concurrently_with_concurrency_option()
    {
        config(['polydock.service_providers_singletons.PolydockServiceProviderFTLagoon' => [
            'ssh_server' => 'ssh.lagoon.test',
            'ssh_port' => '32222',
        ]]);

        // Arrange
        $store = \App\Models\PolydockStore::factory()->create([
            'lagoon_deploy_project_prefix' => 'test-prefix',
        ]);

        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'uuid' => 'test-app-uuid',
            'lagoon_deploy_branch' => 'main',
        ]);

        $instance1 = new PolydockAppInstance;
        $instance1->polydock_store_app_id = $storeApp->id;
        $instance1->name = 'test-instance-1';
        $instance1->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance1->app_type = 'test_app_type';
        $instance1->data = [
            'lagoon-project-name' => 'project-1',
            'lagoon-deploy-branch' => 'main',
            'lagoon-deploy-private-key' => 'test-key',
        ];
        $instance1->saveQuietly();

        $instance2 = new PolydockAppInstance;
        $instance2->polydock_store_app_id = $storeApp->id;
        $instance2->name = 'test-instance-2';
        $instance2->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance2->app_type = 'test_app_type';
        $instance2->data = [
            'lagoon-project-name' => 'project-2',
            'lagoon-deploy-branch' => 'main',
            'lagoon-deploy-private-key' => 'test-key',
        ];
        $instance2->saveQuietly();

        Process::fake();

        // Act
        $this->artisan('polydock:instances:run-lagoon-command', [
            'app_uuid' => $storeApp->uuid,
            'cmd' => 'whoami',
            '--force' => true,
            '--concurrency' => 2,
        ])
            ->expectsOutput('Running commands concurrently on 2 instances (concurrency: 2)...')
            ->assertExitCode(0);

        // Assert
        Process::assertRan(function ($process, $result) {
            $cmd = (string) $process->command;

            return str_contains($cmd, 'ssh') &&
                   str_contains($cmd, 'project-1-main@ssh.lagoon.test') &&
                   str_contains($cmd, 'service=cli container=cli whoami');
        });

        Process::assertRan(function ($process, $result) {
            $cmd = (string) $process->command;

            return str_contains($cmd, 'ssh') &&
                   str_contains($cmd, 'project-2-main@ssh.lagoon.test') &&
                   str_contains($cmd, 'service=cli container=cli whoami');
        });
    }

    public function test_it_skips_instances_missing_metadata()
    {
        // Arrange
        $store = \App\Models\PolydockStore::factory()->create();

        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'uuid' => 'test-app-uuid',
        ]);

        $instance1 = new PolydockAppInstance;
        $instance1->polydock_store_app_id = $storeApp->id;
        $instance1->name = 'test-instance-broken';
        $instance1->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance1->app_type = 'test_app_type';
        $instance1->data = []; // Missing lagoon-project-name
        $instance1->saveQuietly();

        Process::fake();

        // Act
        $this->artisan('polydock:instances:run-lagoon-command', [
            'app_uuid' => $storeApp->uuid,
            'cmd' => 'ls',
            '--force' => true,
        ])
            ->assertExitCode(0);

        Process::assertNotRan(fn ($process) => str_contains((string) $process->command, 'ssh '));
    }
}
