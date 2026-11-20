<?php

namespace Tests\Feature\Console\Commands;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Services\LagoonClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Doubles\FakeLagoonClient;
use Tests\Doubles\FakeLagoonClientService;
use Tests\TestCase;

class RemoveSharedLagoonDefaultUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    private FakeLagoonClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new FakeLagoonClient;
        $this->app->instance(LagoonClientService::class, new FakeLagoonClientService($this->client));

        $instance = new PolydockAppInstance;
        $instance->uuid = 'uuid-1';
        $instance->polydock_store_app_id = PolydockStoreApp::factory()->create([
            'polydock_store_id' => PolydockStore::factory()->create()->id,
        ])->id;
        $instance->name = 'site-b';
        $instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED;
        $instance->app_type = 'test_app_type';
        $instance->data = ['lagoon-project-name' => 'site-b'];
        $instance->saveQuietly();

        $this->client->registerProject('site-b');
        $this->client->groupUsers['project-site-b'] = [
            'default-user@site-a',  // shared key user from another project
            'default-user@site-b',  // the project's own user
            'polydock@example.com',
        ];
        $this->mockConsoleOutput = false;
    }

    public function test_it_removes_all_default_users_but_keeps_real_users(): void
    {
        self::assertSame(0, Artisan::call('polydock:remove-shared-lagoon-default-users', ['--force' => true]));
        self::assertStringContainsString('site-b: removed default-user@site-a', Artisan::output());

        self::assertSame([
            ['group' => 'project-site-b', 'email' => 'default-user@site-a'],
            ['group' => 'project-site-b', 'email' => 'default-user@site-b'],
        ], $this->client->groupUserRemovals);
    }

    public function test_dry_run_removes_nothing(): void
    {
        self::assertSame(0, Artisan::call('polydock:remove-shared-lagoon-default-users', ['--dry-run' => true]));
        self::assertStringContainsString('site-b: would remove default-user@site-a', Artisan::output());

        self::assertSame([], $this->client->groupUserRemovals);
    }

    public function test_it_skips_projects_gone_from_lagoon_and_cleans_removed_instances_that_still_exist(): void
    {
        PolydockAppInstance::query()->update(['status' => PolydockAppInstanceStatus::REMOVED]);

        $gone = PolydockAppInstance::query()->firstOrFail()->replicate();
        $gone->uuid = 'uuid-2';
        $gone->name = 'site-c';
        $gone->data = ['lagoon-project-name' => 'site-c'];
        $gone->saveQuietly();

        self::assertSame(0, Artisan::call('polydock:remove-shared-lagoon-default-users', ['--force' => true]));

        self::assertCount(2, $this->client->groupUserRemovals);
        self::assertSame(['project-site-b'], array_values(array_unique(array_column($this->client->groupUserRemovals, 'group'))));
    }

    public function test_it_skips_adopted_projects(): void
    {
        $instance = PolydockAppInstance::query()->firstOrFail();
        $instance->data = ['lagoon-project-name' => 'site-b', 'adopted' => true];
        $instance->saveQuietly();

        self::assertSame(0, Artisan::call('polydock:remove-shared-lagoon-default-users', ['--force' => true]));

        self::assertSame([], $this->client->groupUserRemovals);
    }
}
