<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\PolydockEngine\PolydockLogger;
use App\Services\LagoonProjectPurgeService;
use App\Services\PurgeResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Doubles\FakeLagoonClient;
use Tests\TestCase;

/**
 * attemptPurge() is the only code path that deletes Lagoon projects — every
 * branch here guards against destroying something that should survive.
 */
class LagoonProjectPurgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeLagoonClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new FakeLagoonClient;
    }

    private function service(): LagoonProjectPurgeService
    {
        return new LagoonProjectPurgeService(new PolydockLogger, $this->client);
    }

    private function makeInstance(string $name = 'purge-me', array $data = []): PolydockAppInstance
    {
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => PolydockStore::factory()->create()->id,
        ]);

        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = $name;
        $instance->app_type = 'test-app';
        $instance->status = PolydockAppInstanceStatus::REMOVED;
        $instance->data = $data;
        $instance->saveQuietly();

        return $instance;
    }

    public function test_adopted_instances_are_never_purged(): void
    {
        $instance = $this->makeInstance('adopted-project', ['adopted' => true]);

        $result = $this->service()->attemptPurge($instance);

        $this->assertSame(PurgeResult::AlreadyGone, $result);
        $this->assertSame([], $this->client->environmentDeletes);
        $this->assertSame([], $this->client->projectDeletes);
    }

    public function test_missing_project_name_is_non_retryable(): void
    {
        $instance = $this->makeInstance('');
        $service = $this->service();

        $result = $service->attemptPurge($instance);

        $this->assertSame(PurgeResult::MissingProjectName, $result);
        $this->assertSame('No Lagoon project name on instance', $service->lastFailureReason);
        $this->assertSame([], $this->client->projectDeletes);
    }

    public function test_unknown_project_counts_as_already_gone(): void
    {
        $instance = $this->makeInstance('vanished-project');

        $result = $this->service()->attemptPurge($instance);

        $this->assertSame(PurgeResult::AlreadyGone, $result);
        $this->assertSame([], $this->client->projectDeletes);
    }

    public function test_active_environments_are_deleted_and_purge_waits(): void
    {
        $this->client->projects['purge-me'] = [
            'id' => 1,
            'name' => 'purge-me',
            'environments' => [
                ['name' => 'main', 'deleted' => null],
                ['name' => 'old-pr', 'deleted' => '2026-01-01 00:00:00'], // already deleted — must be skipped
            ],
        ];
        $instance = $this->makeInstance();
        $service = $this->service();

        $result = $service->attemptPurge($instance);

        $this->assertSame(PurgeResult::StillHasEnvironments, $result);
        $this->assertSame(1, $service->lastEnvironmentCount);
        $this->assertSame(
            [['project' => 'purge-me', 'environment' => 'main']],
            $this->client->environmentDeletes,
        );
        // The project itself must NOT be deleted while environments linger.
        $this->assertSame([], $this->client->projectDeletes);
    }

    public function test_project_with_no_active_environments_is_purged(): void
    {
        $this->client->projects['purge-me'] = [
            'id' => 1,
            'name' => 'purge-me',
            'environments' => [
                ['name' => 'main', 'deleted' => '2026-01-01 00:00:00'],
            ],
        ];
        $instance = $this->makeInstance();

        $result = $this->service()->attemptPurge($instance);

        $this->assertSame(PurgeResult::Purged, $result);
        $this->assertSame(['purge-me'], $this->client->projectDeletes);
        $this->assertSame([], $this->client->environmentDeletes);
    }

    public function test_lagoon_refusing_the_delete_fails_the_attempt(): void
    {
        $this->client->projects['purge-me'] = [
            'id' => 1,
            'name' => 'purge-me',
            'environments' => [],
        ];
        $this->client->deleteProjectResponse = ['error' => 'nope'];
        $instance = $this->makeInstance();
        $service = $this->service();

        $result = $service->attemptPurge($instance);

        $this->assertSame(PurgeResult::Failed, $result);
        $this->assertStringContainsString('deleteProject error', (string) $service->lastFailureReason);
    }

    public function test_lagoon_api_exception_fails_the_attempt(): void
    {
        $this->client->throwOnGetProject = true;
        $instance = $this->makeInstance();
        $service = $this->service();

        $result = $service->attemptPurge($instance);

        $this->assertSame(PurgeResult::Failed, $result);
        $this->assertStringContainsString('getProjectByName threw', (string) $service->lastFailureReason);
        $this->assertSame([], $this->client->projectDeletes);
    }

    public function test_data_project_name_takes_precedence_over_display_name(): void
    {
        $this->client->projects['real-lagoon-name'] = [
            'id' => 1,
            'name' => 'real-lagoon-name',
            'environments' => [],
        ];
        $instance = $this->makeInstance('display-name', ['project_name' => 'real-lagoon-name']);

        $result = $this->service()->attemptPurge($instance);

        $this->assertSame(PurgeResult::Purged, $result);
        $this->assertSame(['real-lagoon-name'], $this->client->projectDeletes);
    }
}
