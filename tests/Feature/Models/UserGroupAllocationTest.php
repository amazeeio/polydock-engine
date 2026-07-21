<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * getNewAppInstanceForThisAppForThisGroup() is the trial allocation path:
 * it must hand a pre-warmed instance to exactly one group (via the
 * allocation_lock) or fall back to creating a fresh one.
 */
class UserGroupAllocationTest extends TestCase
{
    use RefreshDatabase;

    private PolydockStoreApp $storeApp;

    private UserGroup $group;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // keep lifecycle jobs from running

        $this->storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => PolydockStore::factory()->create()->id,
        ]);
        $this->group = UserGroup::factory()->create();
    }

    private function seedUnallocatedInstance(): PolydockAppInstance
    {
        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $this->storeApp->id;
        $instance->name = 'pre-warmed-instance';
        $instance->app_type = 'test-app';
        $instance->status = PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED;
        $instance->user_group_id = null;
        $instance->allocation_lock = null;
        $instance->saveQuietly();

        return $instance;
    }

    public function test_allocates_a_pre_warmed_instance_when_available(): void
    {
        $pooled = $this->seedUnallocatedInstance();

        $allocated = UserGroup::getNewAppInstanceForThisAppForThisGroup($this->storeApp, $this->group);

        $this->assertSame($pooled->id, $allocated->id);
        $this->assertSame($this->group->id, $allocated->user_group_id);
        $this->assertNotNull($allocated->allocation_lock);
        $this->assertSame(PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM, $allocated->fresh()->status);
    }

    public function test_creates_a_fresh_instance_when_the_pool_is_empty(): void
    {
        $allocated = UserGroup::getNewAppInstanceForThisAppForThisGroup($this->storeApp, $this->group);

        $this->assertSame($this->group->id, $allocated->user_group_id);
        // Fresh instances are born NEW; the queued ProcessNewJob (faked here)
        // is what advances them into PENDING_PRE_CREATE.
        $this->assertSame(PolydockAppInstanceStatus::NEW, $allocated->fresh()->status);
    }

    public function test_a_locked_pool_instance_cannot_be_allocated_twice(): void
    {
        $this->seedUnallocatedInstance();
        $otherGroup = UserGroup::factory()->create();

        $first = UserGroup::getNewAppInstanceForThisAppForThisGroup($this->storeApp, $this->group);
        $second = UserGroup::getNewAppInstanceForThisAppForThisGroup($this->storeApp, $otherGroup);

        // The second caller must get a freshly created instance, never the
        // already-locked pooled one.
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($this->group->id, $first->fresh()->user_group_id);
        $this->assertSame($otherGroup->id, $second->user_group_id);
    }

    public function test_custom_named_instances_bypass_the_pool(): void
    {
        $pooled = $this->seedUnallocatedInstance();

        $allocated = UserGroup::getNewAppInstanceForThisAppForThisGroup($this->storeApp, $this->group, 'my-custom-name');

        $this->assertNotSame($pooled->id, $allocated->id);
        $this->assertSame(PolydockAppInstanceStatus::NEW, $allocated->fresh()->status);
        // The pooled instance is left untouched for custom-named requests.
        $this->assertNull($pooled->fresh()->user_group_id);
    }
}
