<?php

namespace Tests\Feature\Api;

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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticatedApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PolydockStoreApp $storeApp;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);

        $this->user = User::factory()->create();

        $store = PolydockStore::create([
            'name' => 'Test Store',
            'status' => 'public',
            'listed_in_marketplace' => true,
            'lagoon_deploy_region_id_ext' => 1,
            'lagoon_deploy_project_prefix' => 'test-prefix',
            'lagoon_deploy_organization_id_ext' => 1,
            'lagoon_deploy_group_name' => 'test-group',
        ]);

        $store->setPolydockVariableValue('lagoon_deploy_private_key', 'dummy-key');

        $this->storeApp = PolydockStoreApp::create([
            'polydock_store_id' => $store->id,
            'polydock_app_class' => 'FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockApp', // A valid class from vendor
            'name' => 'Test App',
            'uuid' => Str::uuid()->toString(),
            'description' => 'Test Application Description',
            'author' => 'Test Author',
            'website' => 'https://example.com',
            'support_email' => 'support@example.com',
            'lagoon_deploy_git' => 'git@github.com:example/repo.git',
            'app_config' => [
                'lagoon_auto_idle' => 1,
                'lagoon_production_environment' => 'prod',
            ],
        ]);

        // PolydockStoreApp boot method assigns a UUID if we didn't provide one, but we provided one.
        // The storeApp needs valid app_class that exists, or we might hit PolydockEngineAppNotFoundException. Let's ensure a mock or valid class exists.
    }

    public function test_get_enums_returns_all_enum_options(): void
    {
        Sanctum::actingAs($this->user, ['instances.read']);

        $response = $this->getJson('/api/enums');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    // 'PolydockAppInstanceStatus',
                    // 'PolydockStoreAppStatus',
                    // 'PolydockStoreStatus',
                    'PolydockStoreWebhookCallStatus',
                    'PolydockVariableScope',
                    'UserGroupRole',
                    'UserRemoteRegistrationStatus',
                    'UserRemoteRegistrationType',
                ],
            ]);

        // $this->assertIsArray($response->json('data.PolydockAppInstanceStatus'));
        // $this->assertArrayHasKey('new', $response->json('data.PolydockAppInstanceStatus'));
        // $this->assertEquals('New', $response->json('data.PolydockAppInstanceStatus.new'));
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->getJson('/api/store-apps');
        $response->assertUnauthorized();
    }

    public function test_get_store_apps_returns_formatted_data(): void
    {
        Sanctum::actingAs($this->user, ['instances.read']);

        $response = $this->getJson('/api/store-apps');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'name',
                        'description',
                        'author', // etc...
                        'store' => [
                            'name',
                            'status',
                            'listed_in_marketplace',
                        ],
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Test App', $response->json('data.0.name'));
        $this->assertEquals('Test Store', $response->json('data.0.store.name'));
    }

    public function test_get_instances_returns_user_instances(): void
    {
        Sanctum::actingAs($this->user, ['instances.read']);

        $group = UserGroup::create(['name' => 'Test Group']);
        $this->user->groups()->attach($group->id, ['role' => 'owner']);

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $group->id,
            'name' => 'test-instance',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);

        $response = $this->getJson('/api/instances?email='.$this->user->email);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($instance->uuid, $response->json('data.0.uuid'));
    }

    public function test_create_instance_provisions_instance_and_creates_user(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $newEmail = 'new.user@example.com';

        $response = $this->postJson('/api/instance', [
            'email' => $newEmail,
            'storeAppId' => $this->storeApp->uuid,
            'config' => [
                'some_key' => 'some_value',
            ],
        ]);

        $response->assertCreated();
        $this->assertArrayHasKey('uuid', $response->json('data'));

        $newUser = User::where('email', $newEmail)->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->primaryGroups()->exists());

        $instanceCount = PolydockAppInstance::where('user_group_id', $newUser->primaryGroups()->first()->id)->count();
        $this->assertEquals(1, $instanceCount);
    }

    public function test_get_instance_status(): void
    {
        Sanctum::actingAs($this->user, ['instances.read']);

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
        ]);

        $instance->storeKeyValue('lagoon-project-name', 'test-lagoon-name');
        $instance->storeKeyValue('lagoon-claim-script', '/app/.lagoon/scripts/polydock_claim.sh');
        $instance->setStatus(PolydockAppInstanceStatus::PRE_CREATE_RUNNING, 'Creating...');
        $instance->save();

        $response = $this->getJson("/api/instance/{$instance->uuid}/status");

        $response->assertOk();
        $this->assertEquals($instance->uuid, $response->json('data.uuid'));
        $this->assertEquals($instance->name, $response->json('data.name'));
        $this->assertEquals($instance->status->value, $response->json('data.status'));
        $this->assertEquals('Creating...', $response->json('data.status_message'));
        $this->assertNull($response->json('data.app_url'));
        $this->assertEquals($this->storeApp->uuid, $response->json('data.store_app.uuid'));
        $this->assertEquals($this->storeApp->name, $response->json('data.store_app.name'));
        $this->assertEquals('git@github.com:example/repo.git', $response->json('data.store_app.git_url'));
        $this->assertNotNull($response->json('data.created_at'));
        $this->assertEquals('/app/.lagoon/scripts/polydock_claim.sh', $response->json('data.lagoon_claim_script'));
        $this->assertEquals('test-lagoon-name', $response->json('data.lagoon_project_name'));
    }

    public function test_delete_instance_sets_pre_remove_status(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
        ]);

        $instance->setStatus(PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED);
        $instance->save();

        $response = $this->deleteJson("/api/instance/{$instance->uuid}");

        $response->assertOk();
        $this->assertEquals('Instance removal initiated', $response->json('message'));

        $instance->refresh();
        $this->assertEquals(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, $instance->status);
    }

    public function test_create_instance_with_custom_name_and_secrets(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $customName = 'My Custom Instance';
        $secret = [
            'llm_key' => 'secret-api-key',
            'llm_url' => 'https://llm.local',
        ];

        $response = $this->postJson('/api/instance', [
            'email' => $this->user->email,
            'storeAppId' => $this->storeApp->uuid,
            'name' => $customName,
            'config' => [
                'secret' => $secret,
            ],
        ]);

        $response->assertCreated();
        $this->assertEquals($customName, $response->json('data.name'));

        $instance = PolydockAppInstance::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($customName, $instance->name);
        $this->assertEquals($secret, $instance->getKeyValue('secret'));
    }

    public function test_create_instance_ensures_unique_name(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $duplicateName = 'duplicate-name';

        // Create first instance
        PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'name' => $duplicateName,
        ]);

        // Create second instance with same name
        $response = $this->postJson('/api/instance', [
            'email' => $this->user->email,
            'storeAppId' => $this->storeApp->uuid,
            'name' => $duplicateName,
        ]);

        $response->assertCreated();
        $newName = $response->json('data.name');

        $this->assertNotEquals($duplicateName, $newName);
        $this->assertStringContainsString($duplicateName, $newName);

        $instance = PolydockAppInstance::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($newName, $instance->name);
        $this->assertEquals($newName, $instance->getKeyValue('lagoon-project-name'));
    }

    public function test_create_instance_with_nested_secrets(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $secret = [
            'ai' => [
                'llm_url' => 'https://ai.example.com',
                'api_key' => 'ai-secret-123',
            ],
            'vector' => [
                'db_host' => 'vectordb.example.com',
                'db_port' => 5432,
            ],
        ];

        $response = $this->postJson('/api/instance', [
            'email' => $this->user->email,
            'storeAppId' => $this->storeApp->uuid,
            'secret' => $secret,
        ]);

        $response->assertCreated();

        $instance = PolydockAppInstance::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($secret, $instance->getKeyValue('secret'));
    }
}
