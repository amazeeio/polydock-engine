<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Status transitions are recorded automatically on every status change and
 * power the per-stage duration helpers (pool wait, new→claimed).
 */
class StatusTransitionTimingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // keep lifecycle jobs from running on status changes
    }

    private function makeInstance(PolydockAppInstanceStatus $status): PolydockAppInstance
    {
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => PolydockStore::factory()->create()->id,
        ]);

        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'timing-test';
        $instance->app_type = 'test-app';
        $instance->status = $status;
        $instance->saveQuietly();

        return $instance;
    }

    public function test_the_creation_row_is_recorded_with_null_from_status(): void
    {
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => PolydockStore::factory()->create()->id,
        ]);

        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'creation-row-test';
        $instance->app_type = 'test-app';
        $instance->status = PolydockAppInstanceStatus::NEW;
        $instance->save(); // NOT quietly — the created event records the row

        $transitions = $instance->statusTransitions;

        $this->assertCount(1, $transitions);
        $this->assertNull($transitions[0]->from_status);
        $this->assertSame(PolydockAppInstanceStatus::NEW, $transitions[0]->to_status);
    }

    public function test_transitions_are_recorded_on_status_changes(): void
    {
        $instance = $this->makeInstance(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED);

        $instance->setStatus(PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM)->save();
        $instance->setStatus(PolydockAppInstanceStatus::POLYDOCK_CLAIM_RUNNING)->save();

        $transitions = $instance->fresh()->statusTransitions;

        $this->assertCount(2, $transitions);
        $this->assertSame(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED, $transitions[0]->from_status);
        $this->assertSame(PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM, $transitions[0]->to_status);
        $this->assertSame(PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM, $transitions[1]->from_status);
        $this->assertSame(PolydockAppInstanceStatus::POLYDOCK_CLAIM_RUNNING, $transitions[1]->to_status);
    }

    public function test_pool_wait_duration_is_computed_from_transitions(): void
    {
        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_POST_DEPLOY);

        // Enters the pool...
        $instance->setStatus(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED)->save();

        // ...sits claimable for 90 seconds...
        $this->travel(90)->seconds();

        // ...then a claim starts.
        $instance->setStatus(PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM)->save();

        $this->assertSame(90, $instance->fresh()->secondsUnclaimedBeforeClaim());
    }

    public function test_creation_to_claimed_duration_uses_instance_created_at(): void
    {
        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_PRE_CREATE);

        $this->travel(300)->seconds();
        $instance->setStatus(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED)->save();

        $this->assertSame(300, $instance->fresh()->secondsFromCreationToClaimed());
    }

    public function test_durations_are_null_when_statuses_were_never_observed(): void
    {
        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_PRE_CREATE);

        $this->assertNull($instance->secondsUnclaimedBeforeClaim());
        $this->assertNull($instance->secondsFromCreationToClaimed());
    }

    public function test_transitions_cascade_delete_with_the_instance(): void
    {
        $instance = $this->makeInstance(PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED);
        $instance->setStatus(PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM)->save();

        $this->assertDatabaseCount('polydock_app_instance_status_transitions', 1);

        $instance->forceDelete();

        $this->assertDatabaseCount('polydock_app_instance_status_transitions', 0);
    }
}
