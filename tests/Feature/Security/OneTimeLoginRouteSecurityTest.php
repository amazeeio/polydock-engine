<?php

namespace Tests\Feature\Security;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class OneTimeLoginRouteSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createAppInstance(array $attributes = []): PolydockAppInstance
    {
        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
        ]);
        $userGroup = UserGroup::factory()->create();

        $instance = new PolydockAppInstance;
        $instance->fill(array_merge([
            'polydock_store_app_id' => $storeApp->id,
            'user_group_id' => $userGroup->id,
            'name' => 'test-instance',
            'app_type' => 'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
            'app_one_time_login_url' => 'https://example.com/login',
            'app_one_time_login_valid_until' => now()->addHour(),
        ], $attributes));

        $instance->uuid = $attributes['uuid'] ?? Str::uuid()->toString();
        $instance->saveQuietly();

        return $instance;
    }

    public function test_unsigned_route_request_is_forbidden(): void
    {
        $instance = $this->createAppInstance();

        $response = $this->get(route('app-instances.show', $instance));
        $response->assertStatus(403);
    }

    public function test_signed_route_request_is_successful(): void
    {
        $instance = $this->createAppInstance();

        $signedUrl = URL::signedRoute('app-instances.show', ['appInstance' => $instance]);

        $response = $this->get($signedUrl);
        $response->assertRedirect('https://example.com/login');
    }
}
