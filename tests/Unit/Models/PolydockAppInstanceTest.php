<?php

namespace Tests\Unit\Models;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Apps\Generic\PolydockAiApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PolydockAppInstanceTest extends TestCase
{
    use RefreshDatabase;

    private PolydockStoreApp $storeApp;

    private UserGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $store = PolydockStore::factory()->create();
        $this->storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
        $this->group = UserGroup::factory()->create();

        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);
    }

    public function test_webhook_url_is_stored_without_token_on_creation(): void
    {
        // GIVEN a token is configured
        Config::set('polydock.health_token', 'test-health-token');

        // WHEN creating a new instance
        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $this->storeApp->id;
        $instance->user_group_id = $this->group->id;
        $instance->name = 'test-dynamic-webhook';
        $instance->app_type = PolydockAiApp::class;
        $instance->status = PolydockAppInstanceStatus::NEW;
        $instance->save();

        // THEN the raw stored URL in the data column has no token
        $rawData = $instance->getRawOriginal('data');
        $dataArray = json_decode($rawData, true);
        $storedUrl = $dataArray['polydock-app-instance-health-webhook-url'];

        $this->assertStringNotContainsString('token=', $storedUrl);

        // BUT when retrieved via getKeyValue(), it dynamically appends the token
        $retrievedUrl = $instance->getKeyValue('polydock-app-instance-health-webhook-url');
        $this->assertStringContainsString('token=test-health-token', $retrievedUrl);
    }

    public function test_webhook_url_updates_when_token_is_rotated(): void
    {
        // GIVEN a token is configured initially
        Config::set('polydock.health_token', 'token-v1');

        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $this->storeApp->id;
        $instance->user_group_id = $this->group->id;
        $instance->name = 'test-dynamic-webhook-rotation';
        $instance->app_type = PolydockAiApp::class;
        $instance->status = PolydockAppInstanceStatus::NEW;
        $instance->save();

        $this->assertStringContainsString('token=token-v1', $instance->getKeyValue('polydock-app-instance-health-webhook-url'));

        // WHEN we rotate the token
        Config::set('polydock.health_token', 'rotated-token-v2');

        // THEN getKeyValue() returns the rotated token immediately without any DB update
        $this->assertStringContainsString('token=rotated-token-v2', $instance->getKeyValue('polydock-app-instance-health-webhook-url'));
        $this->assertStringNotContainsString('token-v1', $instance->getKeyValue('polydock-app-instance-health-webhook-url'));
    }

    public function test_backward_compatibility_with_old_baked_in_tokens(): void
    {
        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $this->storeApp->id;
        $instance->user_group_id = $this->group->id;
        $instance->name = 'test-backward-compatibility';
        $instance->app_type = PolydockAiApp::class;
        $instance->status = PolydockAppInstanceStatus::NEW;
        $instance->save();

        // GIVEN an old instance has a URL with an old baked-in token in the database
        $rawData = $instance->getRawOriginal('data');
        $dataArray = json_decode($rawData, true);
        $dataArray['polydock-app-instance-health-webhook-url'] = 'https://nginx-test.ie1.amazee.io/api/instance/some-uuid/health/?token=old-stale-token';

        // Manually update the database column to simulate old record
        \DB::table('polydock_app_instances')
            ->where('id', $instance->id)
            ->update(['data' => json_encode($dataArray)]);

        $instance->refresh();

        // AND we configure the new rotated token
        Config::set('polydock.health_token', 'new-rotated-token');

        // WHEN we retrieve the webhook URL
        $url = $instance->getKeyValue('polydock-app-instance-health-webhook-url');

        // THEN the old token is stripped and replaced with the new token
        $this->assertStringContainsString('token=new-rotated-token', $url);
        $this->assertStringNotContainsString('old-stale-token', $url);
    }

    public function test_store_key_value_strips_token_before_saving(): void
    {
        $instance = new PolydockAppInstance;
        $instance->polydock_store_app_id = $this->storeApp->id;
        $instance->user_group_id = $this->group->id;
        $instance->name = 'test-store-strips-token';
        $instance->app_type = PolydockAiApp::class;
        $instance->status = PolydockAppInstanceStatus::NEW;
        $instance->save();

        // WHEN storing a webhook URL with a token query param
        $instance->storeKeyValue('polydock-app-instance-health-webhook-url', 'https://domain.com/api/instance/uuid/health/?token=secret-token');

        // THEN the database stored URL has the token stripped
        $rawData = $instance->getRawOriginal('data');
        $dataArray = json_decode($rawData, true);
        $this->assertEquals('https://domain.com/api/instance/uuid/health/', $dataArray['polydock-app-instance-health-webhook-url']);
    }
}
