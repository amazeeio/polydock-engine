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

class AuthenticatedApiController extends Controller
{
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
     * @queryParam email string required The email address of the user. Example: existing.user@example.com
     */
    public function getInstances(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            return response()->json(['data' => []]);
        }

        // Fetch instances for all groups this user is associated with
        $instances = PolydockAppInstance::whereIn('user_group_id', $user->groups()->pluck('user_groups.id'))
            ->with(['storeApp'])
            ->get();

        $formattedInstances = $instances->map(fn (PolydockAppInstance $instance) => [
            'uuid' => $instance->uuid,
            'name' => $instance->name,
            'status' => $instance->status?->value,
            'status_message' => $instance->status_message,
            'app_url' => $instance->app_url,
            'store_app' => [
                'uuid' => $instance->storeApp->uuid,
                'name' => $instance->storeApp->name,
            ],
            'created_at' => $instance->created_at,
        ]);

        return response()->json([
            'data' => $formattedInstances,
        ]);
    }

    /**
     * Create/Provision a new instance
     *
     * Deploy a new PolydockStoreApp instance. If the user associated with the email does not exist, a new user account will automatically be created.
     *
     * @group External API
     *
     * @subgroup Instance Management
     *
     * @bodyParam email email required The email address of the user. Example: new.user@example.com
     * @bodyParam storeAppId string required The UUID of the store app to provision. Example: 3a105da1-9c87-43ca-9ac8-72787fc5e315
     * @bodyParam name string optional The display name for this instance. Defaults to lagoon-project-name if not provided. Example: "My awesome instance"
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
     * @bodyParam config object optional Key-value overrides or configurations for this individual deployment. Example: {"lagoon_auto_idle": "1"}
     *
     * @response 201 {
     *  "message": "Instance provisioned",
     *  "data": {
     *    "uuid": "3a105da1-9c87-43ca-9ac8-72787fc5e315",
     *    "name": "My awesome instance",
     *    "status": "new"
     *  }
     * }
     */
    public function createInstance(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'storeAppId' => 'required|string|exists:polydock_store_apps,uuid',
            'name' => 'nullable|string|max:255',
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

        $email = $request->input('email');

        // @todo Track migration strategy for when a user's static email changes in the future, transitioning to UUIDs.
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Auto', // Dummy default
                'last_name' => 'User',
                'password' => Hash::make(Str::random(32)),
            ]
        );

        // Find or create a default primary user group for this user if they don't have one
        $primaryGroup = $user->primaryGroups()->first();
        if (! $primaryGroup) {
            $primaryGroup = UserGroup::create([
                'name' => "Personal Group - $user->email",
            ]);
            $user->groups()->attach($primaryGroup->id, ['role' => UserGroupRoleEnum::OWNER->value]);
        }

        $storeApp = PolydockStoreApp::where('uuid', $request->input('storeAppId'))->firstOrFail();

        // Apply config if provided
        $config = $request->input('config', []);

        // Use name from request, or fallback to lagoon-project-name from config if provided
        $name = $request->input('name') ?? $config['lagoon-project-name'] ?? null;

        // Use the existing allocation mechanism or create a new instance
        $instance = UserGroup::getNewAppInstanceForThisAppForThisGroup($storeApp, $primaryGroup, $name ? (string) $name : null);

        // Handle top-level secret if provided
        if ($request->has('secret')) {
            $instance->storeKeyValue('secret', $request->input('secret'));
        }

        if (! empty($config)) {
            foreach ($config as $key => $value) {
                // If it's a known root column we could update it, otherwise data blob key-value store
                // Skip if we already stored it from top-level secret to avoid overwriting with potentially partial data
                if ((string) $key === 'secret' && $request->has('secret')) {
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
                'status' => $instance->status?->value,
            ],
        ], 201);
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
     *    "status": "running-healthy-claimed",
     *    "status_message": "Instance is running smoothly.",
     *    "lagoon_claim_script": "/lagoon/polydock_claim.sh",
     *    "lagoon_project_name": "example-project"
     *  }
     * }
     */
    public function getInstanceStatus(string $uuid): JsonResponse
    {
        $instance = PolydockAppInstance::where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'data' => [
                'uuid' => $instance->uuid,
                'status' => $instance->status?->value,
                'status_message' => $instance->status_message,
                'lagoon_claim_script' => $instance->getKeyValue(key: 'lagoon-claim-script'),
                'lagoon_project_name' => $instance->getKeyValue(key: 'lagoon-project-name'),
            ],
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
}
