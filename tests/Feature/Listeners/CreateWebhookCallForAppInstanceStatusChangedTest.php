<?php

declare(strict_types=1);

namespace Tests\Feature\Listeners;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Listeners\CreateWebhookCallForAppInstanceStatusChanged;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\PolydockStoreWebhook;
use App\Models\PolydockStoreWebhookCall;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The app-instance webhook payload is a published contract: consumers hold the
 * instance `uuid` (it is what the API returns on create and the only identifier
 * they ever see), so a payload without it cannot be resolved back to an
 * instance. The identifying fields are pinned here.
 */
class CreateWebhookCallForAppInstanceStatusChangedTest extends TestCase
{
    use RefreshDatabase;

    private function makeInstance(PolydockAppInstanceStatus $status): PolydockAppInstance
    {
        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);

        PolydockStoreWebhook::factory()->active()->create([
            'polydock_store_id' => $store->id,
            'url' => 'https://example.com/webhooks/polydock',
        ]);

        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $storeApp->id;
        $instance->name = 'webhook-payload-test-'.Str::random(6);
        $instance->app_type = 'test-app';
        $instance->status = $status;
        $instance->data = [];
        // saveQuietly() skips the model's creating hook, which is what normally
        // fills the uuid — set it explicitly so this test exercises the payload
        // rather than the model's boot sequence.
        $instance->uuid = (string) Str::uuid();
        $instance->saveQuietly();

        return $instance;
    }

    public function test_created_event_payload_carries_the_app_instance_uuid(): void
    {
        Queue::fake();

        $instance = $this->makeInstance(PolydockAppInstanceStatus::NEW);

        (new CreateWebhookCallForAppInstanceStatusChanged)->handle(
            new PolydockAppInstanceCreatedWithNewStatus($instance),
        );

        $call = PolydockStoreWebhookCall::query()->sole();

        $this->assertSame('app_instance.created', $call->event);
        $this->assertSame($instance->uuid, $call->payload['app_instance_uuid']);
        $this->assertSame($instance->id, $call->payload['app_instance_id']);
        $this->assertNull($call->payload['previous_status']);
        $this->assertSame(
            PolydockAppInstanceStatus::NEW->value,
            $call->payload['current_status'],
        );
    }

    public function test_status_changed_event_payload_carries_the_app_instance_uuid(): void
    {
        Queue::fake();

        $instance = $this->makeInstance(PolydockAppInstanceStatus::PENDING_DEPLOY);

        (new CreateWebhookCallForAppInstanceStatusChanged)->handle(
            new PolydockAppInstanceStatusChanged(
                $instance,
                PolydockAppInstanceStatus::PENDING_PRE_DEPLOY,
            ),
        );

        $call = PolydockStoreWebhookCall::query()->sole();

        $this->assertSame('app_instance.status_changed', $call->event);
        $this->assertSame($instance->uuid, $call->payload['app_instance_uuid']);
        $this->assertSame(
            PolydockAppInstanceStatus::PENDING_PRE_DEPLOY->value,
            $call->payload['previous_status'],
        );
        $this->assertSame(
            PolydockAppInstanceStatus::PENDING_DEPLOY->value,
            $call->payload['current_status'],
        );
    }
}
