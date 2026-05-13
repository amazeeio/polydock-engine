<?php

namespace Tests\Feature\Api;

use App\Enums\UserGroupRoleEnum;
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
                        'git_url',
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
        $this->assertEquals('git@github.com:example/repo.git', $response->json('data.0.git_url'));
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
        $this->assertEquals($group->id, $response->json('data.0.group.id'));
        $this->assertEquals($group->slug, $response->json('data.0.group.slug'));
    }

    public function test_get_groups_returns_groups_for_user_email(): void
    {
        Sanctum::actingAs($this->user, ['instances.read']);

        $group = UserGroup::create(['name' => 'Workspace Group']);
        $this->user->groups()->attach($group->id, ['role' => 'owner']);

        $response = $this->getJson('/api/groups?email='.$this->user->email);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($group->id, $response->json('data.0.id'));
        $this->assertEquals($group->slug, $response->json('data.0.slug'));
        $this->assertEquals('owner', $response->json('data.0.role'));
    }

    public function test_create_group_creates_group_and_assigns_owner(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $response = $this->postJson('/api/groups', [
            'name' => 'Created Workspace',
            'owner_email' => $this->user->email,
        ]);

        $response->assertCreated();
        $groupId = $response->json('data.id');
        $group = UserGroup::find($groupId);

        $this->assertNotNull($group);
        $this->assertEquals('created-workspace', $group->slug);
        $this->assertTrue($this->user->groups()->whereKey($groupId)->exists());
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

    public function test_create_instance_with_group_name_creates_group_for_workspace_flow(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $response = $this->postJson('/api/instance', [
            'email' => $this->user->email,
            'storeAppId' => $this->storeApp->uuid,
            'group_name' => 'Acme Workspace',
        ]);

        $response->assertCreated();
        $groupId = $response->json('data.group.id');
        $group = UserGroup::find($groupId);
        $instance = PolydockAppInstance::where('uuid', $response->json('data.uuid'))->first();

        $this->assertNotNull($group);
        $this->assertEquals('acme-workspace', $group->slug);
        $this->assertEquals($group->id, $instance->user_group_id);
        $this->assertTrue($this->user->groups()->whereKey($group->id)->exists());
    }

    public function test_create_instance_with_existing_group_id_assigns_instance_to_group(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $group = UserGroup::create(['name' => 'Shared Workspace']);

        $response = $this->postJson('/api/instance', [
            'email' => $this->user->email,
            'storeAppId' => $this->storeApp->uuid,
            'group_id' => $group->id,
        ]);

        $response->assertCreated();
        $instance = PolydockAppInstance::where('uuid', $response->json('data.uuid'))->first();

        $this->assertEquals($group->id, $instance->user_group_id);
        $this->assertEquals($group->id, $response->json('data.group.id'));
    }

    public function test_create_instance_with_existing_group_slug_assigns_instance_to_group(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $group = UserGroup::create(['name' => 'Slug Workspace']);
        $this->user->groups()->attach($group->id, ['role' => UserGroupRoleEnum::MEMBER->value]);

        $response = $this->postJson('/api/instance', [
            'email' => $this->user->email,
            'storeAppId' => $this->storeApp->uuid,
            'group_slug' => $group->slug,
        ]);

        $response->assertCreated();
        $instance = PolydockAppInstance::where('uuid', $response->json('data.uuid'))->first();

        $this->assertEquals($group->id, $instance->user_group_id);
        $this->assertEquals($group->slug, $response->json('data.group.slug'));
    }

    public function test_create_instance_provisions_instance_and_creates_user_with_names(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $newEmail = 'named.user@example.com';
        $firstName = 'Jane';
        $lastName = 'Doe';

        $response = $this->postJson('/api/instance', [
            'email' => $newEmail,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'storeAppId' => $this->storeApp->uuid,
        ]);

        $response->assertCreated();

        $newUser = User::where('email', $newEmail)->first();
        $this->assertNotNull($newUser);
        $this->assertEquals($firstName, $newUser->first_name);
        $this->assertEquals($lastName, $newUser->last_name);

        $instance = PolydockAppInstance::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($newEmail, $instance->data['user-email']);
        $this->assertEquals($firstName, $instance->data['user-first-name']);
        $this->assertEquals($lastName, $instance->data['user-last-name']);
    }

    public function test_create_instance_updates_placeholder_names_for_existing_user(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $existingUser = User::factory()->create([
            'email' => 'placeholder@example.com',
            'first_name' => 'Auto',
            'last_name' => 'User',
        ]);

        $firstName = 'Jane';
        $lastName = 'Doe';

        $response = $this->postJson('/api/instance', [
            'email' => $existingUser->email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'storeAppId' => $this->storeApp->uuid,
        ]);

        $response->assertCreated();

        $existingUser->refresh();
        $this->assertEquals($firstName, $existingUser->first_name);
        $this->assertEquals($lastName, $existingUser->last_name);

        $instance = PolydockAppInstance::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($firstName, $instance->data['user-first-name']);
        $this->assertEquals($lastName, $instance->data['user-last-name']);
    }

    public function test_create_instance_does_not_overwrite_real_names_for_existing_user(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $originalFirstName = 'John';
        $originalLastName = 'Smith';
        $existingUser = User::factory()->create([
            'email' => 'real.name@example.com',
            'first_name' => $originalFirstName,
            'last_name' => $originalLastName,
        ]);

        $newFirstName = 'Jane';
        $newLastName = 'Doe';

        $response = $this->postJson('/api/instance', [
            'email' => $existingUser->email,
            'first_name' => $newFirstName,
            'last_name' => $newLastName,
            'storeAppId' => $this->storeApp->uuid,
        ]);

        $response->assertCreated();

        $existingUser->refresh();
        $this->assertEquals($originalFirstName, $existingUser->first_name);
        $this->assertEquals($originalLastName, $existingUser->last_name);

        $instance = PolydockAppInstance::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($originalFirstName, $instance->data['user-first-name']);
        $this->assertEquals($originalLastName, $instance->data['user-last-name']);
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

    public function test_create_instance_stores_and_returns_label(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $label = 'Acme Corp trial instance';

        $response = $this->postJson('/api/instance', [
            'email' => $this->user->email,
            'storeAppId' => $this->storeApp->uuid,
            'label' => $label,
        ]);

        $response->assertCreated();
        $this->assertEquals($label, $response->json('data.label'));

        $instance = PolydockAppInstance::where('uuid', $response->json('data.uuid'))->first();
        $this->assertEquals($label, $instance->getKeyValue('instance-label'));
    }

    public function test_create_instance_without_label_returns_null_label(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $response = $this->postJson('/api/instance', [
            'email' => $this->user->email,
            'storeAppId' => $this->storeApp->uuid,
        ]);

        $response->assertCreated();
        $this->assertNull($response->json('data.label'));
    }

    public function test_get_instances_returns_label(): void
    {
        Sanctum::actingAs($this->user, ['instances.read']);

        $group = UserGroup::create(['name' => 'Label Test Group']);
        $this->user->groups()->attach($group->id, ['role' => 'owner']);

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $group->id,
            'name' => 'labelled-instance',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);
        $instance->storeKeyValue('instance-label', 'My readable label');

        $response = $this->getJson('/api/instances?email='.$this->user->email);

        $response->assertOk();
        $found = collect($response->json('data'))->firstWhere('uuid', $instance->uuid);
        $this->assertNotNull($found);
        $this->assertEquals('My readable label', $found['label']);
    }

    public function test_get_instances_returns_null_label_when_not_set(): void
    {
        Sanctum::actingAs($this->user, ['instances.read']);

        $group = UserGroup::create(['name' => 'No Label Group']);
        $this->user->groups()->attach($group->id, ['role' => 'owner']);

        PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $group->id,
            'name' => 'unlabelled-instance',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);

        $response = $this->getJson('/api/instances?email='.$this->user->email);

        $response->assertOk();
        $found = collect($response->json('data'))->firstWhere('name', 'unlabelled-instance');
        $this->assertNotNull($found);
        $this->assertNull($found['label']);
    }

    public function test_get_instances_can_filter_by_group_id_without_email(): void
    {
        Sanctum::actingAs($this->user, ['instances.read']);

        $targetGroup = UserGroup::create(['name' => 'Target Group']);
        $otherGroup = UserGroup::create(['name' => 'Other Group']);

        $this->user->groups()->syncWithoutDetaching([
            $targetGroup->id => ['role' => UserGroupRoleEnum::MEMBER->value],
        ]);

        $targetInstance = PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $targetGroup->id,
            'name' => 'target-instance',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);

        PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $otherGroup->id,
            'name' => 'other-instance',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);

        $response = $this->getJson('/api/instances?group_id='.$targetGroup->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($targetInstance->uuid, $response->json('data.0.uuid'));
        $this->assertEquals($targetGroup->id, $response->json('data.0.group.id'));
    }

    public function test_get_instances_can_filter_by_group_slug_without_email(): void
    {
        Sanctum::actingAs($this->user, ['instances.read']);

        $targetGroup = UserGroup::create(['name' => 'Slug Target Group']);
        $otherGroup = UserGroup::create(['name' => 'Slug Other Group']);

        $this->user->groups()->syncWithoutDetaching([
            $targetGroup->id => ['role' => UserGroupRoleEnum::MEMBER->value],
        ]);

        $targetInstance = PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $targetGroup->id,
            'name' => 'slug-target-instance',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);

        PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $otherGroup->id,
            'name' => 'slug-other-instance',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);

        $response = $this->getJson('/api/instances?group_slug='.$targetGroup->slug);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($targetInstance->uuid, $response->json('data.0.uuid'));
        $this->assertEquals($targetGroup->slug, $response->json('data.0.group.slug'));
    }

    public function test_get_instances_forbidden_when_not_member_of_group(): void
    {
        Sanctum::actingAs($this->user, ['instances.read']);

        $inaccessibleGroup = UserGroup::create(['name' => 'Inaccessible Group']);

        $response = $this->getJson('/api/instances?group_id='.$inaccessibleGroup->id);

        $response->assertForbidden();
    }

    public function test_assign_instance_to_group_reassigns_existing_instance(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $originalGroup = UserGroup::create(['name' => 'Original Group']);
        $targetGroup = UserGroup::create(['name' => 'Target Migration Group']);

        $this->user->groups()->syncWithoutDetaching([
            $targetGroup->id => ['role' => UserGroupRoleEnum::MEMBER->value],
        ]);

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $originalGroup->id,
            'name' => 'migration-instance',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);

        $response = $this->patchJson('/api/instance/'.$instance->uuid.'/group', [
            'group_id' => $targetGroup->id,
        ]);

        $response->assertOk();
        $instance->refresh();

        $this->assertEquals($targetGroup->id, $instance->user_group_id);
        $this->assertEquals($targetGroup->slug, $response->json('data.group.slug'));
    }

    public function test_assign_instance_to_group_forbidden_when_not_member(): void
    {
        Sanctum::actingAs($this->user, ['instances.write']);

        $originalGroup = UserGroup::create(['name' => 'Original Group']);
        $targetGroup = UserGroup::create(['name' => 'Inaccessible Group']);

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $originalGroup->id,
            'name' => 'migration-instance',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);

        $response = $this->patchJson('/api/instance/'.$instance->uuid.'/group', [
            'group_id' => $targetGroup->id,
        ]);

        $response->assertForbidden();
        $instance->refresh();
        $this->assertEquals($originalGroup->id, $instance->user_group_id);
    }

    public function test_get_instances_wildcard_token_can_query_any_group_without_membership(): void
    {
        // Tokens with '*' ability (service accounts) bypass group membership checks
        // so orchestrators like moad can list instances for any workspace group.
        Sanctum::actingAs($this->user, ['*']);

        $otherUser = \App\Models\User::factory()->create();
        $group = UserGroup::create(['name' => 'Service Account Test Group']);
        $otherUser->groups()->syncWithoutDetaching([
            $group->id => ['role' => UserGroupRoleEnum::MEMBER->value],
        ]);
        // $this->user is NOT a member of $group

        PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $group->id,
            'name' => 'sa-test-instance',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);

        $response = $this->getJson('/api/instances?group_id='.$group->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_get_instances_read_only_token_still_forbidden_for_non_member_group(): void
    {
        // Regular user tokens (instances.read, not *) still enforce group membership.
        Sanctum::actingAs($this->user, ['instances.read']);

        $inaccessibleGroup = UserGroup::create(['name' => 'Still Inaccessible Group']);

        $response = $this->getJson('/api/instances?group_id='.$inaccessibleGroup->id);

        $response->assertForbidden();
    }

    public function test_assign_instance_to_group_wildcard_token_bypasses_membership_check(): void
    {
        // Tokens with '*' ability (service accounts) can assign instances to any group.
        Sanctum::actingAs($this->user, ['*']);

        $originalGroup = UserGroup::create(['name' => 'SA Original Group']);
        $targetGroup = UserGroup::create(['name' => 'SA Target Group']);
        // $this->user is NOT a member of either group

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $this->storeApp->id,
            'user_group_id' => $originalGroup->id,
            'name' => 'sa-assign-instance',
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);

        $response = $this->patchJson('/api/instance/'.$instance->uuid.'/group', [
            'group_id' => $targetGroup->id,
        ]);

        $response->assertOk();
        $instance->refresh();
        $this->assertEquals($targetGroup->id, $instance->user_group_id);
    }
}
