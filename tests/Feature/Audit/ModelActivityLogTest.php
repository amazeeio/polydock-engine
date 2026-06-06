<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Enums\PolydockStoreStatusEnum;
use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Models\UserGroup;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ModelActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);
    }

    public function test_polydock_app_instance_logs_creation(): void
    {
        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
        $group = UserGroup::factory()->create();

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $storeApp->id,
            'user_group_id' => $group->id,
        ]);

        $activity = Activity::where('subject_type', PolydockAppInstance::class)
            ->where('subject_id', $instance->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($instance->uuid, $activity->properties['instance_uuid']);
    }

    public function test_polydock_app_instance_ignores_status_only_updates(): void
    {
        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
        $group = UserGroup::factory()->create();

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $storeApp->id,
            'user_group_id' => $group->id,
        ]);

        // Refresh to get clean state from DB
        $instance->refresh();

        // Clear all activities from creation
        Activity::query()->delete();

        // Update only status (should NOT generate an activity row because
        // status and status_message are not in the logOnly list)
        $instance->status = PolydockAppInstanceStatus::PENDING_CREATE;
        $instance->status_message = 'Processing...';
        $instance->save();

        $activities = Activity::where('subject_type', PolydockAppInstance::class)
            ->where('subject_id', $instance->id)
            ->where('event', 'updated')
            ->get();

        $this->assertCount(0, $activities);
    }

    public function test_polydock_app_instance_logs_user_group_id_change(): void
    {
        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
        $group1 = UserGroup::factory()->create();
        $group2 = UserGroup::factory()->create();

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $storeApp->id,
            'user_group_id' => $group1->id,
        ]);

        Activity::query()->delete();

        $instance->user_group_id = $group2->id;
        $instance->save();

        $activity = Activity::where('subject_type', PolydockAppInstance::class)
            ->where('subject_id', $instance->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($group1->id, $activity->properties['old']['user_group_id']);
        $this->assertEquals($group2->id, $activity->properties['attributes']['user_group_id']);
    }

    public function test_user_group_logs_creation(): void
    {
        $group = UserGroup::create(['name' => 'Audit Test Group']);

        $activity = Activity::where('subject_type', UserGroup::class)
            ->where('subject_id', $group->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
    }

    public function test_user_group_logs_name_change(): void
    {
        $group = UserGroup::create(['name' => 'Original Name']);
        Activity::query()->delete();

        $group->name = 'New Name';
        $group->save();

        $activity = Activity::where('subject_type', UserGroup::class)
            ->where('subject_id', $group->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('Original Name', $activity->properties['old']['name']);
        $this->assertEquals('New Name', $activity->properties['attributes']['name']);
    }

    public function test_user_logs_email_change(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        Activity::query()->delete();

        $user->email = 'new@example.com';
        $user->save();

        $activity = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('old@example.com', $activity->properties['old']['email']);
        $this->assertEquals('new@example.com', $activity->properties['attributes']['email']);
    }

    public function test_user_does_not_log_password_change(): void
    {
        $user = User::factory()->create();
        Activity::query()->delete();

        $user->password = 'new-secret-password';
        $user->save();

        $activities = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('event', 'updated')
            ->get();

        // Should not log because password is not in logOnly list
        $this->assertCount(0, $activities);
    }

    public function test_polydock_store_logs_status_change(): void
    {
        $store = PolydockStore::factory()->create(['status' => 'public']);
        Activity::query()->delete();

        $store->status = PolydockStoreStatusEnum::PRIVATE;
        $store->save();

        $activity = Activity::where('subject_type', PolydockStore::class)
            ->where('subject_id', $store->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
    }
}
