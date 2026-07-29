<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\PollEnsureUnallocatedAppInstancesJobCommand;
use App\Enums\PolydockStoreAppStatusEnum;
use App\Jobs\EnsureUnallocatedAppInstancesJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

/**
 * Pins the pool-maintenance truth table for the poll tick after the
 * aggregate-query optimization: deficit dispatches, satisfied pool doesn't.
 */
class PollUnallocatedInstancesCheckTest extends TestCase
{
    use RefreshDatabase;

    private PolydockStoreApp $storeApp;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => PolydockStore::factory()->create()->id,
            'status' => PolydockStoreAppStatusEnum::AVAILABLE,
            'target_unallocated_app_instances' => 2,
        ]);
    }

    private function command(): PollEnsureUnallocatedAppInstancesJobCommand
    {
        $command = new PollEnsureUnallocatedAppInstancesJobCommand;
        $command->setLaravel($this->app);
        $command->setInput(new ArrayInput([]));
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

        return $command;
    }

    private function seedPooledInstance(): void
    {
        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $this->storeApp->id;
        $instance->name = 'pool-'.Str::random(6);
        $instance->app_type = 'test-app';
        $instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED;
        $instance->user_group_id = null;
        $instance->saveQuietly();
    }

    public function test_deficit_dispatches_the_maintenance_job(): void
    {
        // Target 2, pool 0 => maintenance needed.
        $count = $this->command()->checkOnce();

        $this->assertSame(1, $count);
        Queue::assertPushedOn('unallocated-instance-creation', EnsureUnallocatedAppInstancesJob::class);
    }

    public function test_satisfied_pool_dispatches_nothing(): void
    {
        $this->seedPooledInstance();
        $this->seedPooledInstance();

        $count = $this->command()->checkOnce();

        $this->assertSame(0, $count);
        Queue::assertNothingPushed();
    }
}
