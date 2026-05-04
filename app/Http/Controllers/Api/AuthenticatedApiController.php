<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\PolydockStoreAppStatusEnum;
use App\Enums\PolydockStoreStatusEnum;
use App\Enums\PolydockStoreWebhookCallStatusEnum;
use App\Enums\PolydockVariableScopeEnum;
use App\Enums\UserGroupRoleEnum;
use App\Enums\UserRemoteRegistrationStatusEnum;
use App\Enums\UserRemoteRegistrationType;
use App\Http\Controllers\Controller;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use App\Models\User;
use App\Models\UserGroup;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticatedApiController extends Controller
{
    /**
     * Get groups by user email
     *
     * Retrieve all groups associated with a specific user's email address.
     *
     * @group External API
     *
     * @subgroup Group Management
     *
     * @queryParam email string required The email address of the user. Example: existing.user@example.com
     */
    public function getGroups(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            return response()->json(['data' => []]);
        }

        $groups = $user->groups()
            ->select('user_groups.*', 'user_user_group.role')
            ->orderBy('user_groups.name')
            ->get();

        return response()->json([
            'data' => $groups->map(fn (UserGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'role' => $group->pivot?->role,
            ]),
        ]);
    }

    /**
     * Create a new group
     *
     * Create a group and optionally attach an owner by email. This supports workspace creation in upstream systems.
     *
     * @group External API
     *
     * @subgroup Group Management
     *
     * @bodyParam name string required Human-readable group name. Example: Acme Workspace
     * @bodyParam owner_email email optional Existing user email to attach as group owner. Example: owner@example.com
     *
     * @response 201 {
     *  "message": "Group created",
     *  "data": {
     *    "id": 12,
     *    "name": "Acme Workspace",
     *    "slug": "acme-workspace"
     *  }
     * }
     */
    public function createGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'owner_email' => 'nullable|email|exists:users,email',
        ]);

        $group = UserGroup::create([
            'name' => $validated['name'],
        ]);

        if (! empty($validated['owner_email'])) {
            $owner = User::where('email', $validated['owner_email'])->firstOrFail();
            $owner->groups()->syncWithoutDetaching([
                $group->id => ['role' => UserGroupRoleEnum::OWNER->value],
            ]);
        }

        return response()->json([
            'message' => 'Group created',
            'data' => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
            ],
        ], 201);
    }

    /**
     * Get all store apps
     *
     * Retrieve a list of all available apps across all Polydock stores that can be provisioned.
     *
     * @group External API
     *
     * @subgroup Store Management
     */
    public function getStoreApps(): JsonResponse
    {
        $apps = PolydockStoreApp::with('store')
            ->where('status', PolydockStoreAppStatusEnum::AVAILABLE)
            ->whereHas('store', function ($query): void {
                $query->where('status', PolydockStoreStatusEnum::PUBLIC);
            })
            ->get();

        $formattedApps = $apps->map(fn (PolydockStoreApp $app) => [
            'uuid' => $app->uuid,
            'name' => $app->name,
            'description' => $app->description,
            'author' => $app->author,
            'available_for_trials' => $app->available_for_trials,
            'app_status' => $app->status?->value,
            'git_url' => $app->lagoon_deploy_git,
            'store' => [
                'name' => $app->store->name,
                'status' => $app->store->status?->value,
                'listed_in_marketplace' => $app->store->listed_in_marketplace,
            ],
        ]);

        return response()->json([
            'data' => $formattedApps,
        ]);
    }

    /**
     * Get selected enums
     *
     * Retrieve a list of selected enums used in the Polydock API and their possible values/labels.
     *
     * @group External API
     *
     * @subgroup Meta
     */
    public function getEnums(): JsonResponse
    {
        return response()->json([
            'data' => [
                // 'PolydockAppInstanceStatus' => PolydockAppInstanceStatus::getEnumOptions(),
                // 'PolydockStoreAppStatus' => PolydockStoreAppStatusEnum::getEnumOptions(),
                // 'PolydockStoreStatus' => PolydockStoreStatusEnum::getEnumOptions(),
                'PolydockStoreWebhookCallStatus' => PolydockStoreWebhookCallStatusEnum::getEnumOptions(),
                'PolydockVariableScope' => PolydockVariableScopeEnum::getEnumOptions(),
                'UserGroupRole' => UserGroupRoleEnum::getEnumOptions(),
                'UserRemoteRegistrationStatus' => UserRemoteRegistrationStatusEnum::getEnumOptions(),
                'UserRemoteRegistrationType' => UserRemoteRegistrationType::getEnumOptions(),
            ],
        ]);
    }

    /**
     * Get instances by user email
     *
     * Retrieve all provisioned instances tied to a specific user's email address.
     *
     * @group External API
     *
     * @subgroup Instance Management
     *
     * @queryParam email string optional Limit results to groups associated with this email address. Example: existing.user@example.com
     * @queryParam group_id integer optional Limit results to a specific group id the user belongs to. Example: 12
     * @queryParam group_slug string optional Limit results to a specific group slug the user belongs to. Example: acme-workspace
     */
    public function getInstances(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'nullable|email',
            'group_id' => 'nullable|integer|exists:user_groups,id',
            'group_slug' => 'nullable|string|exists:user_groups,slug',
        ]);

        if (! $request->filled('email') && ! $request->filled('group_id') && ! $request->filled('group_slug')) {
            throw ValidationException::withMessages([
                'email' => ['At least one of email, group_id, or group_slug is required.'],
            ]);
        }

        if ($request->filled('group_id') && $request->filled('group_slug')) {
            throw ValidationException::withMessages([
                'group_id' => ['Only one of group_id or group_slug may be provided.'],
                'group_slug' => ['Only one of group_id or group_slug may be provided.'],
            ]);
        }

        $targetGroup = null;

        if (isset($validated['group_id'])) {
            $targetGroup = UserGroup::findOrFail($validated['group_id']);
        } elseif (isset($validated['group_slug'])) {
            $targetGroup = UserGroup::where('slug', $validated['group_slug'])->firstOrFail();
        }

        /** @var User $tokenUser */
        $tokenUser = $request->user();

        if ($targetGroup !== null && ! $tokenUser->groups()->whereKey($targetGroup->id)->exists()) {
            abort(403, 'You do not have access to the selected group.');
        }

        $instanceQuery = PolydockAppInstance::query()->with(['storeApp', 'userGroup']);

        if ($request->filled('email')) {
            $user = User::where('email', $validated['email'])->first();

            if (! $user) {
                return response()->json(['data' => []]);
            }

            $instanceQuery->whereIn('user_group_id', $user->groups()->pluck('user_groups.id'));
        }

        if ($targetGroup !== null) {
            $instanceQuery->where('user_group_id', $targetGroup->id);
        }

        $instances = $instanceQuery->get();

        $formattedInstances = $instances->map(fn (PolydockAppInstance $instance) => [
            'uuid' => $instance->uuid,
            'name' => $instance->name,
            'label' => $instance->getKeyValue('instance-label') ?: null,
            'status' => $instance->status?->value,
            'status_message' => $instance->status_message,
            'app_url' => $instance->app_url,
            'store_app' => [
                'uuid' => $instance->storeApp->uuid,
                'name' => $instance->storeApp->name,
            ],
            'group' => $instance->userGroup ? [
                'id' => $instance->userGroup->id,
                'name' => $instance->userGroup->name,
                'slug' => $instance->userGroup->slug,
            ] : null,
            'created_at' => $instance->created_at,
        ]);

        return response()->json([
            'data' => $formattedInstances,
        ]);
    }

    /**
     * Create/Provision a new instance
     *
     * Deploy a new PolydockStoreApp instance. If the user associated with the email does not exist, a new user account will automatically be created using the provided first and last names. For existing users, names will be updated only if current values are placeholders or empty.
     *
     * @group External API
     *
     * @subgroup Instance Management
     *
     * @bodyParam email email required The email address of the user. Example: new.user@example.com
     * @bodyParam first_name string optional The first name of the user. Example: Jane
     * @bodyParam last_name string optional The last name of the user. Example: Doe
     * @bodyParam storeAppId string required The UUID of the store app to provision. Example: 3a105da1-9c87-43ca-9ac8-72787fc5e315
     * @bodyParam name string optional The display name for this instance. Defaults to lagoon-project-name if not provided. Example: "My awesome instance"
     * @bodyParam label string optional A free-form human-readable label for this instance. Not used as an identifier; may contain spaces and special characters. Example: "Acme Corp trial"
     * @bodyParam group_id integer optional Existing group id to provision the instance into. If omitted, the user's primary group is used or created. Example: 12
     * @bodyParam group_slug string optional Existing group slug to provision the instance into. If omitted, the user's primary group is used or created. Example: acme-workspace
     * @bodyParam group_name string optional Create a new group with this name and provision the instance into it. Example: Acme Workspace
     * @bodyParam secret object optional Sensitive AI and VectorDB credentials. Example: {"ai": {"llm_url": "https://llm", "api_key": "sk-123"}, "vector": {"db_host": "localhost", "db_port": 5432, "db_name": "db_d1234", "db_user": "admin", "db_pass": "pass"}}
     * @bodyParam secret.ai object optional AI LLM configuration.
     * @bodyParam secret.ai.llm_url string optional The LLM API base URL. Example: https://llm.local
     * @bodyParam secret.ai.api_key string optional The LLM API key. Example: sk-123...
     * @bodyParam secret.vector object optional Vector Database configuration.
     * @bodyParam secret.vector.db_host string optional The database host. Example: localhost
     * @bodyParam secret.vector.db_port int optional The database port. Example: 5432
     * @bodyParam secret.vector.db_name string optional The database name. Example: db_d1234
     * @bodyParam secret.vector.db_user string optional The database username. Example: admin
     * @bodyParam secret.vector.db_pass string optional The database password. Example: secret-pass
     * @bodyParam config object optional Key-value overrides or configurations for this individual deployment. Example: {"lagoon-auto-idle": "1"}
     *
     * @response 201 {
     *  "message": "Instance provisioned",
     *  "data": {
     *    "uuid": "3a105da1-9c87-43ca-9ac8-72787fc5e315",
     *    "name": "My awesome instance",
     *    "label": "Acme Corp trial",
     *    "status": "new"
     *  }
     * }
     */
    public function createInstance(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'storeAppId' => 'required|string|exists:polydock_store_apps,uuid',
            'name' => 'nullable|string|max:255',
            'label' => 'nullable|string|max:255',
            'group_id' => 'nullable|integer|exists:user_groups,id',
            'group_slug' => 'nullable|string|exists:user_groups,slug',
            'group_name' => 'nullable|string|max:255',
            'secret' => 'nullable|array',
            'secret.ai' => 'nullable|array',
            'secret.ai.llm_url' => 'nullable|string',
            'secret.ai.api_key' => 'nullable|string',
            'secret.vector' => 'nullable|array',
            'secret.vector.db_host' => 'nullable|string',
            'secret.vector.db_port' => 'nullable|integer',
            'secret.vector.db_name' => 'nullable|string',
            'secret.vector.db_user' => 'nullable|string',
            'secret.vector.db_pass' => 'nullable|string',
            'config' => 'nullable|array',
            'config.*' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    // Allow 'secret' to be an array if passed within config (legacy support)
                    if (str_ends_with($attribute, '.secret')) {
                        if (! \is_array($value)) {
                            $fail("The {$attribute} must be an array.");
                        }

                        return;
                    }

                    if (\is_array($value) || \is_object($value)) {
                        $fail("The {$attribute} must be a scalar value.");
                    }
                },
            ],
        ]);

        if ($request->filled('group_id') && $request->filled('group_slug')) {
            throw ValidationException::withMessages([
                'group_id' => ['Only one of group_id or group_slug may be provided.'],
                'group_slug' => ['Only one of group_id or group_slug may be provided.'],
            ]);
        }

        if ($request->filled('group_name') && ($request->filled('group_id') || $request->filled('group_slug'))) {
            throw ValidationException::withMessages([
                'group_name' => ['group_name cannot be combined with group_id or group_slug.'],
            ]);
        }

        $email = $request->input('email');

        // @todo Track migration strategy for when a user's static email changes in the future, transitioning to UUIDs.
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => $request->filled('first_name') ? $request->input('first_name') : 'Auto', // Dummy default
                'last_name' => $request->filled('last_name') ? $request->input('last_name') : 'User',
                'password' => Hash::make(Str::random(32)),
            ]
        );

        // Update user names cautiously for existing users:
        // Only replace placeholder/empty names, and do not arbitrarily overwrite real names.
        if (! $user->wasRecentlyCreated) {
            if (
                $request->filled('first_name') &&
                (\is_null($user->first_name) || $user->first_name === '' || $user->first_name === 'Auto')
            ) {
                $user->first_name = $request->input('first_name');
            }
            if (
                $request->filled('last_name') &&
                (\is_null($user->last_name) || $user->last_name === '' || $user->last_name === 'User')
            ) {
                $user->last_name = $request->input('last_name');
            }
        }
        if ($user->isDirty()) {
            $user->save();
        }

        $primaryGroup = $this->resolveTargetGroup($request, $user);

        $storeApp = PolydockStoreApp::where('uuid', $request->input('storeAppId'))->firstOrFail();

        // Apply config if provided
        $config = $request->input('config', []);

        // Use name from request, or fallback to lagoon-project-name from config if provided
        $name = $request->input('name') ?? $config['lagoon-project-name'] ?? null;

        // Use the existing allocation mechanism or create a new instance
        $instance = UserGroup::getNewAppInstanceForThisAppForThisGroup($storeApp, $primaryGroup, $name ? (string) $name : null);

        // Add user information to the app instance data - this enables claiming
        $instance->data = array_merge($instance->data ?? [], [
            'user-email' => $user->email,
            'user-first-name' => $user->first_name,
            'user-last-name' => $user->last_name,
        ]);
        $instance->save();

        // Store the optional free-form label
        if ($request->filled('label')) {
            $instance->storeKeyValue('instance-label', $request->input('label'));
        }

        // Handle top-level secret if provided
        if ($request->filled('secret')) {
            $instance->storeKeyValue('secret', $request->input('secret'));
        }

        if (! empty($config)) {
            foreach ($config as $key => $value) {
                // If it's a known root column we could update it, otherwise data blob key-value store
                // Skip if we already stored it from top-level secret to avoid overwriting with potentially partial data
                if ((string) $key === 'secret' && $request->filled('secret')) {
                    continue;
                }
                $instance->storeKeyValue((string) $key, $value === null ? '' : $value);
            }
        }

        return response()->json([
            'message' => 'Instance provisioned',
            'data' => [
                'uuid' => $instance->uuid,
                'name' => $instance->name,
                'label' => $instance->getKeyValue('instance-label') ?: null,
                'group' => [
                    'id' => $primaryGroup->id,
                    'name' => $primaryGroup->name,
                    'slug' => $primaryGroup->slug,
                ],
                'status' => $instance->status?->value,
            ],
        ], 201);
    }

    /**
     * Assign an instance to an existing group
     *
     * Reassign an existing app instance to another group. Intended for migration and backfill workflows.
     *
     * @group External API
     *
     * @subgroup Instance Management
     *
     * @urlParam uuid string required The UUID of the instance. Example: 3a105da1-9c87-43ca-9ac8-72787fc5e315
     *
     * @bodyParam group_id integer optional Existing group id to assign the instance to. Example: 12
     * @bodyParam group_slug string optional Existing group slug to assign the instance to. Example: acme-workspace
     */
    public function assignInstanceToGroup(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => 'nullable|integer|exists:user_groups,id',
            'group_slug' => 'nullable|string|exists:user_groups,slug',
        ]);

        if (! $request->filled('group_id') && ! $request->filled('group_slug')) {
            throw ValidationException::withMessages([
                'group_id' => ['Either group_id or group_slug is required.'],
            ]);
        }

        if ($request->filled('group_id') && $request->filled('group_slug')) {
            throw ValidationException::withMessages([
                'group_id' => ['Only one of group_id or group_slug may be provided.'],
                'group_slug' => ['Only one of group_id or group_slug may be provided.'],
            ]);
        }

        $instance = PolydockAppInstance::where('uuid', $uuid)->firstOrFail();

        $group = isset($validated['group_id'])
            ? UserGroup::findOrFail($validated['group_id'])
            : UserGroup::where('slug', $validated['group_slug'])->firstOrFail();

        /** @var User $user */
        $user = $request->user();

        if (! $user->groups()->whereKey($group->id)->exists()) {
            abort(403, 'You do not have access to the selected group.');
        }

        $instance->user_group_id = $group->id;
        $instance->save();

        return response()->json([
            'message' => 'Instance assigned to group',
            'data' => [
                'uuid' => $instance->uuid,
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'slug' => $group->slug,
                ],
            ],
        ]);
    }

    /**
     * Get instance status
     *
     * Retrieve the current provisioning or health status of a specific instance using its UUID.
     *
     * @group External API
     *
     * @subgroup Instance Management
     *
     * @urlParam uuid string required The UUID of the instance. Example: 3a105da1-9c87-43ca-9ac8-72787fc5e315
     *
     * @response {
     *  "data": {
     *    "uuid": "3a105da1-9c87-43ca-9ac8-72787fc5e315",
     *    "name": "my-instance",
     *    "status": "running-healthy-claimed",
     *    "status_message": "Instance is running smoothly.",
     *    "app_url": "https://my-instance.example.com",
     *    "store_app": {
     *      "uuid": "7b206eb2-1d98-54db-0bd9-83898gd6f426",
     *      "name": "My App",
     *      "git_url": "git@github.com:example/repo.git"
     *    },
     *    "created_at": "2025-01-01T00:00:00.000000Z",
     *    "lagoon_claim_script": "/lagoon/polydock_claim.sh",
     *    "lagoon_project_name": "example-project"
     *  }
     * }
     */
    public function getInstanceStatus(string $uuid): JsonResponse
    {
        $instance = PolydockAppInstance::where('uuid', $uuid)->with('storeApp')->firstOrFail();

        $data = [
            'uuid' => $instance->uuid,
            'name' => $instance->name,
            'status' => $instance->status?->value,
            'status_message' => $instance->status_message,
            'app_url' => $instance->app_url,
            'store_app' => [
                'uuid' => $instance->storeApp->uuid,
                'name' => $instance->storeApp->name,
                'git_url' => $instance->storeApp->lagoon_deploy_git,
            ],
            'created_at' => $instance->created_at,
            'lagoon_claim_script' => $instance->getKeyValue(key: 'lagoon-claim-script'),
            'lagoon_project_name' => $instance->getKeyValue(key: 'lagoon-project-name'),
        ];

        // Include credentials if they exist and instance is at least in claimed status
        if ($instance->status === PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED) {
            $data['app_admin_username'] = $instance->getGeneratedAppAdminUsername();
            $data['app_admin_password'] = $instance->getGeneratedAppAdminPassword();
            $data['app_admin_api_key'] = $instance->getKeyValue('app-admin-api-key');
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Delete an instance
     *
     * Remove an app instance via its Unique Identifier. This will asynchronously initiate the removal process.
     *
     * @group External API
     *
     * @subgroup Instance Management
     *
     * @urlParam uuid string required The UUID of the instance to delete. Example: 3a105da1-9c87-43ca-9ac8-72787fc5e315
     *
     * @response {
     *  "message": "Instance removal initiated",
     *  "data": {
     *    "uuid": "3a105da1-9c87-43ca-9ac8-72787fc5e315",
     *    "status": "pending-pre-remove"
     *  }
     * }
     */
    public function deleteInstance(string $uuid): JsonResponse
    {
        $instance = PolydockAppInstance::where('uuid', $uuid)->firstOrFail();

        // Initiate the deletion process by setting the status
        $instance->setStatus(PolydockAppInstanceStatus::PENDING_PRE_REMOVE, 'Deletion requested via API');
        $instance->save();

        return response()->json([
            'message' => 'Instance removal initiated',
            'data' => [
                'uuid' => $instance->uuid,
                'status' => $instance->status?->value,
            ],
        ]);
    }

    private function resolveTargetGroup(Request $request, User $user): UserGroup
    {
        if ($request->filled('group_id')) {
            return UserGroup::findOrFail($request->integer('group_id'));
        }

        if ($request->filled('group_slug')) {
            return UserGroup::where('slug', $request->string('group_slug')->toString())->firstOrFail();
        }

        if ($request->filled('group_name')) {
            $group = UserGroup::create([
                'name' => $request->string('group_name')->toString(),
            ]);

            $user->groups()->syncWithoutDetaching([
                $group->id => ['role' => UserGroupRoleEnum::OWNER->value],
            ]);

            return $group;
        }

        $primaryGroup = $user->primaryGroups()->first();
        if ($primaryGroup) {
            return $primaryGroup;
        }

        $primaryGroup = UserGroup::create([
            'name' => "Personal Group - $user->email",
        ]);
        $user->groups()->attach($primaryGroup->id, ['role' => UserGroupRoleEnum::OWNER->value]);

        return $primaryGroup;
    }
}
