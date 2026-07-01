<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Jobs\EnsureUnallocatedAppInstancesJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MaintainPreWarmInstancesCommandTest extends TestCase
{
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // The removal path assigns instances to the configured default user group,
        // so that group must exist to satisfy the foreign key. The env in this
        // environment leaves the config empty, so pin it to a real group here.
        $defaultGroup = UserGroup::factory()->create();
        config()->set('polydock.default_user_group_id_for_unallocated_instances', $defaultGroup->id);
    }

    #[\Override]
    protected function tearDown(): void
    {
        PolydockAppInstance::flushEventListeners();
        parent::tearDown();
    }

    private function createStoreApp(int $target = 1): PolydockStoreApp
    {
        $store = PolydockStore::factory()->create();

        return PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'target_unallocated_app_instances' => $target,
        ]);
    }

    private function createUnallocatedInstance(
        PolydockStoreApp $storeApp,
        PolydockAppInstanceStatus $status = PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED,
    ): PolydockAppInstance {
        $instance = new PolydockAppInstance;
        $instance->uuid = 'test-'.uniqid();
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'test-instance';
        $instance->status = $status;
        $instance->app_type = 'test_app_type';
        $instance->data = [];
        $instance->user_group_id = null;
        $instance->saveQuietly();

        return $instance;
    }

    public function test_dispatches_refill_job_even_when_no_maintenance_needed(): void
    {
        // Target 1, exactly one unallocated instance -> no excess, nothing to remove.
        $storeApp = $this->createStoreApp(target: 1);
        $instance = $this->createUnallocatedInstance($storeApp);

        $this->artisan('polydock:maintain-prewarm-instances', ['--force' => true])
            ->assertExitCode(0);

        Queue::assertPushed(EnsureUnallocatedAppInstancesJob::class, 1);

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $instance->status);
    }

    public function test_queues_excess_instances_for_removal(): void
    {
        // Target 1, three unallocated -> two excess should be queued for removal.
        $storeApp = $this->createStoreApp(target: 1);
        $a = $this->createUnallocatedInstance($storeApp);
        $b = $this->createUnallocatedInstance($storeApp);
        $c = $this->createUnallocatedInstance($storeApp);

        $this->artisan('polydock:maintain-prewarm-instances', ['--force' => true])
            ->expectsOutputToContain('Queued 2 pre-warm instance(s)')
            ->assertExitCode(0);

        Queue::assertPushed(EnsureUnallocatedAppInstancesJob::class, 1);

        $removed = collect([$a, $b, $c])
            ->each->refresh()
            ->filter(fn ($i) => $i->status === PolydockAppInstanceStatus::PENDING_PRE_REMOVE);

        $this->assertCount(2, $removed);
    }

    public function test_does_not_queue_removal_when_at_target(): void
    {
        $storeApp = $this->createStoreApp(target: 2);
        $a = $this->createUnallocatedInstance($storeApp);
        $b = $this->createUnallocatedInstance($storeApp);

        $this->artisan('polydock:maintain-prewarm-instances', ['--force' => true])
            ->assertExitCode(0);

        $a->refresh();
        $b->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $a->status);
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $b->status);

        Queue::assertPushed(EnsureUnallocatedAppInstancesJob::class, 1);
    }

    public function test_app_filter_limits_maintenance_to_named_app(): void
    {
        $targetApp = $this->createStoreApp(target: 1);
        $targetExcess1 = $this->createUnallocatedInstance($targetApp);
        $targetExcess2 = $this->createUnallocatedInstance($targetApp);

        $otherApp = $this->createStoreApp(target: 1);
        $otherExcess1 = $this->createUnallocatedInstance($otherApp);
        $otherExcess2 = $this->createUnallocatedInstance($otherApp);

        $this->artisan('polydock:maintain-prewarm-instances', [
            '--app' => $targetApp->uuid,
            '--force' => true,
        ])->assertExitCode(0);

        $targetRemoved = collect([$targetExcess1, $targetExcess2])
            ->each->refresh()
            ->filter(fn ($i) => $i->status === PolydockAppInstanceStatus::PENDING_PRE_REMOVE);
        $this->assertCount(1, $targetRemoved);

        // The other app should be untouched.
        $otherExcess1->refresh();
        $otherExcess2->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $otherExcess1->status);
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $otherExcess2->status);

        // The unconditional refill job is still dispatched exactly once.
        Queue::assertPushed(EnsureUnallocatedAppInstancesJob::class, 1);
    }

    public function test_refresh_all_queues_all_removable_instances_regardless_of_target(): void
    {
        // At target (2 of 2): normal runs would remove nothing.
        $storeApp = $this->createStoreApp(target: 2);
        $a = $this->createUnallocatedInstance($storeApp);
        $b = $this->createUnallocatedInstance($storeApp);

        $this->artisan('polydock:maintain-prewarm-instances', [
            '--refresh-all' => true,
            '--force' => true,
        ])->assertExitCode(0);

        // With --refresh-all, every removable instance is queued for removal
        // even though the pool is at target.
        $a->refresh();
        $b->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $a->status);
        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $b->status);

        Queue::assertPushed(EnsureUnallocatedAppInstancesJob::class, 1);
    }

    public function test_confirmation_prompt_aborts_without_force(): void
    {
        $storeApp = $this->createStoreApp(target: 1);
        $excess = $this->createUnallocatedInstance($storeApp);
        $this->createUnallocatedInstance($storeApp);

        $this->artisan('polydock:maintain-prewarm-instances')
            ->expectsConfirmation('Do you want to queue pre-warm maintenance for these apps?', 'no')
            ->expectsOutputToContain('Operation cancelled by user.')
            ->assertExitCode(0);

        $excess->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $excess->status);

        Queue::assertNotPushed(EnsureUnallocatedAppInstancesJob::class);
    }

    public function test_reports_when_no_store_apps_match(): void
    {
        $this->artisan('polydock:maintain-prewarm-instances', [
            '--app' => '00000000-0000-0000-0000-000000000000',
            '--force' => true,
        ])
            ->expectsOutputToContain('No store apps found matching the given filters.')
            ->assertExitCode(0);

        Queue::assertNotPushed(EnsureUnallocatedAppInstancesJob::class);
    }
}
