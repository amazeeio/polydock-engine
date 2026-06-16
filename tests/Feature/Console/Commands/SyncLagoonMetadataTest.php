<?php

namespace Tests\Feature\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockProductType;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Services\LagoonClientService;
use FreedomtechHosting\FtLagoonPhp\Client;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncLagoonMetadataTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_it_syncs_metadata_for_active_instances(): void
    {
        $this->setupLagoonKey();

        // Create product type
        $productType = PolydockProductType::create([
            'name' => 'Amazee Claw',
        ]);

        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'polydock_product_type_id' => $productType->id,
        ]);

        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-instance-uuid-1';
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'test-instance-1';
        $instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance->app_type = 'test_app_type';
        $instance->data = [
            'lagoon-project-name' => 'project-1',
            'user-email' => 'test@example.com',
            'user-first-name' => 'John',
            'user-last-name' => 'Doe',
        ];
        $instance->saveQuietly();

        // Mock Lagoon Client
        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();

        $mock->shouldReceive('getProjectByName')
            ->with('project-1')
            ->once()
            ->andReturn([
                'projectByName' => [
                    'metadata' => [],
                ],
            ]);

        // Expect metadata syncs
        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-1', 'email', 'test@example.com')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-1', 'product-type', 'amazee-claw')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-1', 'firstname', 'John')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-1', 'lastname', 'Doe')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-1', 'polydock-env', 'dev')
            ->once()
            ->andReturn(['id' => 1]);

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:sync-metadata', [
            '--force' => true,
        ])
            ->expectsOutput('Found 1 active app instance(s) to sync.')
            ->expectsOutput('Syncing metadata for test-instance-1 (Project: project-1)...')
            ->assertExitCode(0);
    }

    public function test_it_respects_filters(): void
    {
        $this->setupLagoonKey();

        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);

        $instance1 = new PolydockAppInstance;
        $instance1->uuid = 'uuid-1';
        $instance1->polydock_store_app_id = $storeApp->id;
        $instance1->name = 'instance-1';
        $instance1->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance1->app_type = 'test_app_type';
        $instance1->data = [
            'lagoon-project-name' => 'project-1',
            'user-email' => 'one@example.com',
        ];
        $instance1->saveQuietly();

        $instance2 = new PolydockAppInstance;
        $instance2->uuid = 'uuid-2';
        $instance2->polydock_store_app_id = $storeApp->id;
        $instance2->name = 'instance-2';
        $instance2->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance2->app_type = 'test_app_type';
        $instance2->data = [
            'lagoon-project-name' => 'project-2',
            'user-email' => 'two@example.com',
        ];
        $instance2->saveQuietly();

        // Mock Client to only expect instance2 since we will filter by UUID
        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();

        $mock->shouldReceive('getProjectByName')
            ->with('project-2')
            ->once()
            ->andReturn([
                'projectByName' => [
                    'metadata' => [],
                ],
            ]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-2', 'email', 'two@example.com')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-2', 'product-type', 'generic')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-2', 'polydock-env', 'dev')
            ->once()
            ->andReturn(['id' => 1]);

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:sync-metadata', [
            '--uuid' => 'uuid-2',
            '--force' => true,
        ])
            ->expectsOutput('Found 1 active app instance(s) to sync.')
            ->expectsOutput('Syncing metadata for instance-2 (Project: project-2)...')
            ->assertExitCode(0);
    }

    public function test_it_syncs_polydock_env_as_prod_in_production_environment(): void
    {
        $this->setupLagoonKey();

        // Configure lagoon_environment_type to production
        config(['polydock.lagoon_environment_type' => 'production']);

        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);

        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-instance-prod-uuid';
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'test-instance-prod';
        $instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance->app_type = 'test_app_type';
        $instance->data = [
            'lagoon-project-name' => 'project-prod',
            'user-email' => 'prod@example.com',
        ];
        $instance->saveQuietly();

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();

        $mock->shouldReceive('getProjectByName')
            ->with('project-prod')
            ->once()
            ->andReturn([
                'projectByName' => [
                    'metadata' => [],
                ],
            ]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-prod', 'email', 'prod@example.com')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-prod', 'product-type', 'generic')
            ->once()
            ->andReturn(['id' => 1]);

        // Expect polydock-env to be prod
        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-prod', 'polydock-env', 'prod')
            ->once()
            ->andReturn(['id' => 1]);

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:sync-metadata', [
            '--uuid' => 'test-instance-prod-uuid',
            '--force' => true,
        ])
            ->expectsOutput('Found 1 active app instance(s) to sync.')
            ->expectsOutput('Syncing metadata for test-instance-prod (Project: project-prod)...')
            ->assertExitCode(0);
    }

    public function test_it_respects_app_id_filter(): void
    {
        $this->setupLagoonKey();

        $store = PolydockStore::factory()->create();
        $storeApp1 = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
        $storeApp2 = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);

        $instance1 = new PolydockAppInstance;
        $instance1->uuid = 'uuid-app-1';
        $instance1->polydock_store_app_id = $storeApp1->id;
        $instance1->name = 'instance-app-1';
        $instance1->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance1->app_type = 'test_app_type';
        $instance1->data = [
            'lagoon-project-name' => 'project-app-1',
            'user-email' => 'app1@example.com',
        ];
        $instance1->saveQuietly();

        $instance2 = new PolydockAppInstance;
        $instance2->uuid = 'uuid-app-2';
        $instance2->polydock_store_app_id = $storeApp2->id;
        $instance2->name = 'instance-app-2';
        $instance2->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance2->app_type = 'test_app_type';
        $instance2->data = [
            'lagoon-project-name' => 'project-app-2',
            'user-email' => 'app2@example.com',
        ];
        $instance2->saveQuietly();

        // Only expect instance2 because we filter by storeApp2->id
        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();

        $mock->shouldReceive('getProjectByName')
            ->with('project-app-2')
            ->once()
            ->andReturn([
                'projectByName' => [
                    'metadata' => [],
                ],
            ]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-app-2', 'email', 'app2@example.com')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-app-2', 'product-type', 'generic')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-app-2', 'polydock-env', 'dev')
            ->once()
            ->andReturn(['id' => 1]);

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:sync-metadata', [
            '--app-id' => $storeApp2->id,
            '--force' => true,
        ])
            ->expectsOutput('Found 1 active app instance(s) to sync.')
            ->expectsOutput('Syncing metadata for instance-app-2 (Project: project-app-2)...')
            ->assertExitCode(0);
    }

    public function test_it_respects_email_filter(): void
    {
        $this->setupLagoonKey();

        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);

        $instance1 = new PolydockAppInstance;
        $instance1->uuid = 'uuid-email-1';
        $instance1->polydock_store_app_id = $storeApp->id;
        $instance1->name = 'instance-email-1';
        $instance1->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance1->app_type = 'test_app_type';
        $instance1->data = [
            'lagoon-project-name' => 'project-email-1',
            'user-email' => 'target@example.com',
        ];
        $instance1->saveQuietly();

        $instance2 = new PolydockAppInstance;
        $instance2->uuid = 'uuid-email-2';
        $instance2->polydock_store_app_id = $storeApp->id;
        $instance2->name = 'instance-email-2';
        $instance2->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance2->app_type = 'test_app_type';
        $instance2->data = [
            'lagoon-project-name' => 'project-email-2',
            'user-email' => 'other@example.com',
        ];
        $instance2->saveQuietly();

        // Only expect instance1 because we filter by email case-insensitively
        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();

        $mock->shouldReceive('getProjectByName')
            ->with('project-email-1')
            ->once()
            ->andReturn([
                'projectByName' => [
                    'metadata' => [],
                ],
            ]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-email-1', 'email', 'target@example.com')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-email-1', 'product-type', 'generic')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-email-1', 'polydock-env', 'dev')
            ->once()
            ->andReturn(['id' => 1]);

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:sync-metadata', [
            '--email' => 'TARGET@example.com',
            '--force' => true,
        ])
            ->expectsOutput('Found 1 active app instance(s) to sync.')
            ->expectsOutput('Syncing metadata for instance-email-1 (Project: project-email-1)...')
            ->assertExitCode(0);
    }

    public function test_it_respects_limit_option(): void
    {
        $this->setupLagoonKey();

        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);

        $instance1 = new PolydockAppInstance;
        $instance1->uuid = 'uuid-lim-1';
        $instance1->polydock_store_app_id = $storeApp->id;
        $instance1->name = 'instance-lim-1';
        $instance1->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance1->app_type = 'test_app_type';
        $instance1->data = [
            'lagoon-project-name' => 'project-lim-1',
            'user-email' => 'lim1@example.com',
        ];
        $instance1->saveQuietly();

        $instance2 = new PolydockAppInstance;
        $instance2->uuid = 'uuid-lim-2';
        $instance2->polydock_store_app_id = $storeApp->id;
        $instance2->name = 'instance-lim-2';
        $instance2->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance2->app_type = 'test_app_type';
        $instance2->data = [
            'lagoon-project-name' => 'project-lim-2',
            'user-email' => 'lim2@example.com',
        ];
        $instance2->saveQuietly();

        // Only expect first instance since we limit to 1
        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();

        $mock->shouldReceive('getProjectByName')
            ->with('project-lim-1')
            ->once()
            ->andReturn([
                'projectByName' => [
                    'metadata' => [],
                ],
            ]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-lim-1', 'email', 'lim1@example.com')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-lim-1', 'product-type', 'generic')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-lim-1', 'polydock-env', 'dev')
            ->once()
            ->andReturn(['id' => 1]);

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:sync-metadata', [
            '--limit' => 1,
            '--force' => true,
        ])
            ->expectsOutput('Found 1 active app instance(s) to sync.')
            ->expectsOutput('Syncing metadata for instance-lim-1 (Project: project-lim-1)...')
            ->assertExitCode(0);
    }

    public function test_it_skips_writing_when_metadata_already_matches(): void
    {
        $this->setupLagoonKey();

        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);

        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-instance-skip-uuid';
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'test-instance-skip';
        $instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance->app_type = 'test_app_type';
        $instance->data = [
            'lagoon-project-name' => 'project-skip',
            'user-email' => 'skip@example.com',
        ];
        $instance->saveQuietly();

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();

        // getProjectByName returns exactly what we are about to set
        $mock->shouldReceive('getProjectByName')
            ->with('project-skip')
            ->once()
            ->andReturn([
                'projectByName' => [
                    'metadata' => [
                        'email' => 'skip@example.com',
                        'product-type' => 'generic',
                        'polydock-env' => 'dev',
                    ],
                ],
            ]);

        // updateProjectMetadata should NOT be called at all!
        $mock->shouldNotReceive('updateProjectMetadata');

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:sync-metadata', [
            '--uuid' => 'test-instance-skip-uuid',
            '--force' => true,
        ])
            ->expectsOutput('Found 1 active app instance(s) to sync.')
            ->expectsOutput('Syncing metadata for test-instance-skip (Project: project-skip)...')
            ->expectsOutput('  - All metadata already in sync.')
            ->assertExitCode(0);
    }

    public function test_it_handles_get_project_by_name_failure_leniently_and_proceeds_with_writes(): void
    {
        $this->setupLagoonKey();

        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);

        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-instance-lenient-uuid';
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'test-instance-lenient';
        $instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance->app_type = 'test_app_type';
        $instance->data = [
            'lagoon-project-name' => 'project-lenient',
            'user-email' => 'lenient@example.com',
        ];
        $instance->saveQuietly();

        $mock = \Mockery::mock(Client::class);
        $mock->shouldReceive('setLagoonToken')->with('fake-token')->once();
        $mock->shouldReceive('initGraphqlClient')->once();

        // getProjectByName throws an exception (e.g. transient API failure)
        $mock->shouldReceive('getProjectByName')
            ->with('project-lenient')
            ->once()
            ->andThrow(new \Exception('Connection timeout to GraphQL API'));

        // It should proceed with safety writes because of our lenient handling!
        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-lenient', 'email', 'lenient@example.com')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-lenient', 'product-type', 'generic')
            ->once()
            ->andReturn(['id' => 1]);

        $mock->shouldReceive('updateProjectMetadata')
            ->with('project-lenient', 'polydock-env', 'dev')
            ->once()
            ->andReturn(['id' => 1]);

        $this->app->instance(Client::class, $mock);

        $this->artisan('polydock:sync-metadata', [
            '--uuid' => 'test-instance-lenient-uuid',
            '--force' => true,
        ])
            ->expectsOutput('Found 1 active app instance(s) to sync.')
            ->expectsOutput('Syncing metadata for test-instance-lenient (Project: project-lenient)...')
            ->expectsOutput('  - Failed to fetch existing project metadata: Connection timeout to GraphQL API. Proceeding with safety writes.')
            ->expectsOutput('  - Set metadata: email => lenient@example.com')
            ->expectsOutput('  - Set metadata: product-type => generic')
            ->expectsOutput('  - Set metadata: polydock-env => dev')
            ->assertExitCode(0);
    }

    public function test_it_restricts_get_authenticated_client_overrides(): void
    {
        $this->setupLagoonKey();

        /** @var LagoonClientService $service */
        $service = app(LagoonClientService::class);

        // Call with allowed and non-allowed override keys
        $client = $service->getAuthenticatedClient([
            'timeout' => 99.0,
            'connect_timeout' => 88.0,
            'ssh_user' => 'malicious-user-hack', // Should be ignored/filtered out!
            'endpoint' => 'https://malicious-endpoint.hack/graphql', // Should be ignored/filtered out!
        ]);

        // Use reflection to assert protected fields on Client
        $refClient = new \ReflectionClass($client);

        $propUser = $refClient->getProperty('lagoonSshUser');
        $propUser->setAccessible(true);
        // Should remain default 'lagoon' rather than 'malicious-user-hack'
        $this->assertEquals('lagoon', $propUser->getValue($client));

        $propEndpoint = $refClient->getProperty('lagoonApiEndpoint');
        $propEndpoint->setAccessible(true);
        // Should remain default value 'https://api.lagoon.amazeeio.cloud/graphql' rather than 'https://malicious-endpoint.hack/graphql'
        $this->assertEquals('https://api.lagoon.amazeeio.cloud/graphql', $propEndpoint->getValue($client));

        $propConfig = $refClient->getProperty('config');
        $propConfig->setAccessible(true);
        $config = $propConfig->getValue($client);

        // Assert that the allowlisted overrides are preserved
        $this->assertEquals(99.0, $config['timeout']);
        $this->assertEquals(88.0, $config['connect_timeout']);

        // Assert that the non-allowlisted override values were ignored/filtered out
        $this->assertEquals('lagoon', $config['ssh_user'] ?? null);
        $this->assertEquals('https://api.lagoon.amazeeio.cloud/graphql', $config['endpoint'] ?? null);
    }
}
