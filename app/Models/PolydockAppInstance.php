<?php

namespace App\Models;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInterface;
use App\Polydock\Core\PolydockAppLoggerInterface;
use App\Polydock\Core\PolydockEngineInterface;
use App\PolydockEngine\Helpers\AmazeeAiBackendHelper;
use App\PolydockEngine\PolydockEngineAppNotFoundException;
use App\Traits\HasPolydockVariables;
use App\Traits\HasWebhookSensitiveData;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $name
 * @property int $polydock_store_app_id
 * @property int|null $user_group_id
 * @property string $app_type
 * @property PolydockAppInstanceStatus $status
 * @property string|null $status_message
 * @property bool $is_trial
 * @property Carbon|null $trial_ends_at
 * @property bool $trial_completed
 * @property Carbon|null $send_midtrial_email_at
 * @property bool $midtrial_email_sent
 * @property Carbon|null $send_one_day_left_email_at
 * @property bool $one_day_left_email_sent
 * @property bool $trial_complete_email_sent
 * @property string|null $app_url
 * @property string|null $app_one_time_login_url
 * @property Carbon|null $app_one_time_login_valid_until
 * @property array|null $data
 * @property string $uuid
 * @property Carbon|null $removed_at
 * @property Carbon|null $purge_eligible_at
 * @property Carbon|null $force_purge_requested_at
 * @property int $purge_attempts
 * @property Carbon|null $purge_last_attempted_at
 * @property string|null $purge_failure_reason
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property PolydockStoreApp $storeApp
 * @property UserGroup|null $userGroup
 * @property PolydockDeploymentRun|null $deploymentRun
 */
class PolydockAppInstance extends Model implements PolydockAppInstanceInterface
{
    use HasPolydockVariables;
    use HasWebhookSensitiveData;
    use LogsActivity;
    use SoftDeletes;

    /**
     * The fillable attributes for the model
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'polydock_store_app_id',
        'user_group_id',
        'app_type',
        'status',
        'status_message',
        'is_trial',
        'trial_ends_at',
        'trial_completed',
        'send_midtrial_email_at',
        'midtrial_email_sent',
        'send_one_day_left_email_at',
        'one_day_left_email_sent',
        'trial_complete_email_sent',
        'app_url',
        'app_one_time_login_url',
        'app_one_time_login_valid_until',
        'removed_at',
        'purge_eligible_at',
        'force_purge_requested_at',
        'purge_attempts',
        'purge_last_attempted_at',
        'purge_failure_reason',
        'deployment_run_id',
        'last_deployment_name',
        'last_deployment_status',
        'last_deployed_at',
        'last_deploy_triggered_at',
        'next_redeploy_at',
    ];

    /**
     * The casts for the model
     *
     * @var array
     */
    protected $casts = [
        'status' => PolydockAppInstanceStatus::class,
        'data' => 'array',
        'is_trial' => 'boolean',
        'trial_ends_at' => 'datetime',
        'trial_completed' => 'boolean',
        'send_midtrial_email_at' => 'datetime',
        'midtrial_email_sent' => 'boolean',
        'send_one_day_left_email_at' => 'datetime',
        'one_day_left_email_sent' => 'boolean',
        'trial_complete_email_sent' => 'boolean',
        'app_one_time_login_valid_until' => 'datetime',
        'removed_at' => 'datetime',
        'purge_eligible_at' => 'datetime',
        'force_purge_requested_at' => 'datetime',
        'purge_last_attempted_at' => 'datetime',
        'purge_attempts' => 'integer',
        'last_deployed_at' => 'datetime',
        'last_deploy_triggered_at' => 'datetime',
        'next_redeploy_at' => 'datetime',
    ];

    /**
     * The engine for the app instance
     */
    private PolydockEngineInterface $engine;

    /**
     * The app for the app instance
     */
    private PolydockAppInterface $app;

    /**
     * The logger for the app instance
     */
    private PolydockAppLoggerInterface $logger;

    // Add default sensitive keys specific to app instances
    protected array $sensitiveDataKeys = [
        // Exact matches
        'private_key',
        'secret',
        'password',
        'token',
        'api_key',
        'ssh_key',
        'lagoon_deploy_private_key',
        'recaptcha',

        // Regex patterns
        '/^.*_key$/',              // Anything ending with _key
        '/^.*_secret$/',           // Anything ending with _secret
        '/^.*password.*$/',        // Anything containing password
        '/^.*username.*$/',        // Anything containing password
        '/^.*token.*$/',           // Anything containing token
        '/^.*api[_-]?key.*$/i',    // Any variation of api key
        '/^.*ssh[_-]?key.*$/i',    // Any variation of ssh key
        '/^.*private[_-]?key.*$/i', // Any variation of private key
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'user_group_id',
                'is_trial',
                'trial_ends_at',
                'app_url',
                'removed_at',
                'force_purge_requested_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        if ($eventName === 'created') {
            $activity->properties = $activity->properties->merge([
                'instance_uuid' => $this->uuid,
                'store_app' => $this->storeApp?->name,
            ]);
        }
    }

    public static array $pendingStatuses = [
        PolydockAppInstanceStatus::PENDING_PRE_CREATE,
        PolydockAppInstanceStatus::PENDING_CREATE,
        PolydockAppInstanceStatus::PENDING_POST_CREATE,
        PolydockAppInstanceStatus::PENDING_PRE_DEPLOY,
        PolydockAppInstanceStatus::PENDING_DEPLOY,
        PolydockAppInstanceStatus::PENDING_POST_DEPLOY,
        PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
        PolydockAppInstanceStatus::PENDING_REMOVE,
        PolydockAppInstanceStatus::PENDING_POST_REMOVE,
        PolydockAppInstanceStatus::PENDING_PRE_UPGRADE,
        PolydockAppInstanceStatus::PENDING_UPGRADE,
        PolydockAppInstanceStatus::PENDING_POST_UPGRADE,
        PolydockAppInstanceStatus::PENDING_PURGE,
    ];

    public static array $completedStatuses = [
        PolydockAppInstanceStatus::PRE_CREATE_COMPLETED,
        PolydockAppInstanceStatus::CREATE_COMPLETED,
        PolydockAppInstanceStatus::POST_CREATE_COMPLETED,
        PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED,
        PolydockAppInstanceStatus::DEPLOY_COMPLETED,
        PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED,
        PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED,
        PolydockAppInstanceStatus::REMOVE_COMPLETED,
        PolydockAppInstanceStatus::POST_REMOVE_COMPLETED,
        PolydockAppInstanceStatus::PRE_UPGRADE_COMPLETED,
        PolydockAppInstanceStatus::UPGRADE_COMPLETED,
        PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED,
        PolydockAppInstanceStatus::POLYDOCK_CLAIM_COMPLETED,
    ];

    public static array $failedStatuses = [
        PolydockAppInstanceStatus::PRE_CREATE_FAILED,
        PolydockAppInstanceStatus::CREATE_FAILED,
        PolydockAppInstanceStatus::POST_CREATE_FAILED,
        PolydockAppInstanceStatus::PRE_DEPLOY_FAILED,
        PolydockAppInstanceStatus::DEPLOY_FAILED,
        PolydockAppInstanceStatus::POST_DEPLOY_FAILED,
        PolydockAppInstanceStatus::PRE_REMOVE_FAILED,
        PolydockAppInstanceStatus::REMOVE_FAILED,
        PolydockAppInstanceStatus::POST_REMOVE_FAILED,
        PolydockAppInstanceStatus::PRE_UPGRADE_FAILED,
        PolydockAppInstanceStatus::UPGRADE_FAILED,
        PolydockAppInstanceStatus::POST_UPGRADE_FAILED,
        PolydockAppInstanceStatus::POLYDOCK_CLAIM_FAILED,
        PolydockAppInstanceStatus::PURGE_FAILED,
    ];

    public static array $pollingStatuses = [
        PolydockAppInstanceStatus::DEPLOY_RUNNING,
        PolydockAppInstanceStatus::UPGRADE_RUNNING,
        PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED,
        PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
        PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE,
    ];

    public static array $stageCreateStatuses = [
        PolydockAppInstanceStatus::NEW,
        PolydockAppInstanceStatus::PENDING_PRE_CREATE,
        PolydockAppInstanceStatus::PENDING_CREATE,
        PolydockAppInstanceStatus::PENDING_POST_CREATE,
        PolydockAppInstanceStatus::PRE_CREATE_RUNNING,
        PolydockAppInstanceStatus::CREATE_RUNNING,
        PolydockAppInstanceStatus::POST_CREATE_RUNNING,
        PolydockAppInstanceStatus::PRE_CREATE_COMPLETED,
        PolydockAppInstanceStatus::CREATE_COMPLETED,
        PolydockAppInstanceStatus::POST_CREATE_COMPLETED,
    ];

    public static array $stageDeployStatuses = [
        PolydockAppInstanceStatus::PENDING_PRE_DEPLOY,
        PolydockAppInstanceStatus::PENDING_DEPLOY,
        PolydockAppInstanceStatus::PENDING_POST_DEPLOY,
        PolydockAppInstanceStatus::PRE_DEPLOY_RUNNING,
        PolydockAppInstanceStatus::DEPLOY_RUNNING,
        PolydockAppInstanceStatus::POST_DEPLOY_RUNNING,
        PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED,
        PolydockAppInstanceStatus::DEPLOY_COMPLETED,
        PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED,
    ];

    public static array $stageRemoveStatuses = [
        PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
        PolydockAppInstanceStatus::PENDING_REMOVE,
        PolydockAppInstanceStatus::PENDING_POST_REMOVE,
        PolydockAppInstanceStatus::PRE_REMOVE_RUNNING,
        PolydockAppInstanceStatus::REMOVE_RUNNING,
        PolydockAppInstanceStatus::POST_REMOVE_RUNNING,
        PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED,
        PolydockAppInstanceStatus::REMOVE_COMPLETED,
        PolydockAppInstanceStatus::POST_REMOVE_COMPLETED,
        PolydockAppInstanceStatus::REMOVED,
    ];

    public static array $stagePurgeStatuses = [
        PolydockAppInstanceStatus::PENDING_PURGE,
        PolydockAppInstanceStatus::PURGE_RUNNING,
        PolydockAppInstanceStatus::PURGE_FAILED,
    ];

    public static array $stageUpgradeStatuses = [
        PolydockAppInstanceStatus::PENDING_PRE_UPGRADE,
        PolydockAppInstanceStatus::PENDING_UPGRADE,
        PolydockAppInstanceStatus::PENDING_POST_UPGRADE,
        PolydockAppInstanceStatus::UPGRADE_RUNNING,
        PolydockAppInstanceStatus::PRE_UPGRADE_COMPLETED,
        PolydockAppInstanceStatus::UPGRADE_COMPLETED,
        PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED,
        PolydockAppInstanceStatus::PRE_UPGRADE_RUNNING,
        PolydockAppInstanceStatus::UPGRADE_RUNNING,
        PolydockAppInstanceStatus::POST_UPGRADE_RUNNING,
    ];

    public static array $stageRunningStatuses = [
        PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED,
        PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
        PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE,
    ];

    public static array $stageClaimStatuses = [
        PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM,
        PolydockAppInstanceStatus::POLYDOCK_CLAIM_RUNNING,
        PolydockAppInstanceStatus::POLYDOCK_CLAIM_COMPLETED,
    ];

    /**
     * Statuses in which an instance may be safely redeployed (upgrade rollout).
     * Only healthy running instances qualify — the pre-warm pool
     * (RUNNING_HEALTHY_UNCLAIMED) and live claimed apps (RUNNING_HEALTHY_CLAIMED).
     *
     * @var array<int, PolydockAppInstanceStatus>
     */
    public static array $redeployEligibleStatuses = [
        PolydockAppInstanceStatus::RUNNING_HEALTHY_UNCLAIMED,
        PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
    ];

    /**
     * Statuses indicating an unallocated instance is progressing through the
     * create → deploy → claim pipeline. Used to count in-progress pool instances
     * and to detect stuck instances.
     *
     * @return array<int, PolydockAppInstanceStatus>
     */
    public static function unallocatedInProgressStatuses(): array
    {
        return [
            PolydockAppInstanceStatus::NEW,
            ...self::$stageCreateStatuses,
            ...self::$stageDeployStatuses,
            ...self::$stageClaimStatuses,
        ];
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    #[\Override]
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    /**
     * Boot the model.
     */
    #[\Override]
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Get the store app and its class
            $storeApp = PolydockStoreApp::findOrFail($model->polydock_store_app_id);

            try {
                $model->status = PolydockAppInstanceStatus::NEW->value;
                $model->status_message = PolydockAppInstanceStatus::NEW->getStatusMessage();

                // Set the app type using the store app's class
                $model->setAppType($storeApp->polydock_app_class);

                if (empty($model->name)) {
                    $model->name = $model->generateUniqueProjectName($storeApp->lagoon_deploy_project_prefix, $storeApp);
                }

                // Ensure name uniqueness
                $baseName = $model->name;
                while (static::where('name', $model->name)->exists()) {
                    $model->name = $baseName.'-'.strtolower(Str::random(4));
                }

                // Fill the UUID
                $model->uuid = Str::uuid()->toString();

                $data = [
                    'uuid' => $model->uuid,
                    'lagoon-deploy-git' => $storeApp->lagoon_deploy_git,
                    'lagoon-deploy-branch' => $storeApp->lagoon_deploy_branch,
                    'lagoon-deploy-organization-id' => $storeApp->lagoon_deploy_organization_id_ext,
                    'lagoon-deploy-group-name' => $storeApp->lagoon_deploy_group_name,
                    'lagoon-deploy-region-id' => $storeApp->lagoon_deploy_region_id_ext,
                    'lagoon-deploy-private-key' => $storeApp->lagoon_deploy_private_key,
                    'lagoon-deploy-project-prefix' => $storeApp->lagoon_deploy_project_prefix,
                    'lagoon-project-name' => $model->name,
                    'amazee-ai-backend-region-id' => $storeApp->amazee_ai_backend_region_id_ext,
                    'available-for-trials' => $storeApp->available_for_trials,
                    'lagoon-auto-idle' => $storeApp->lagoon_auto_idle,
                    'lagoon-production-environment' => $storeApp->lagoon_production_environment,
                    'lagoon-generate-app-admin-username' => $model->generateUniqueUsername(),
                    'lagoon-generate-app-admin-password' => $model->generateUniquePassword(),
                    'polydock-app-instance-health-webhook-url' => str_replace(':status:', '', route('api.instance.health', [
                        'uuid' => $model->uuid,
                        'status' => ':status:',
                    ], true)),
                ];

                $data = array_merge($data, self::getDataForLagoonScript($storeApp, 'post_deploy', 'post-deploy'));
                $data = array_merge($data, self::getDataForLagoonScript($storeApp, 'pre_upgrade', 'pre-upgrade'));
                $data = array_merge($data, self::getDataForLagoonScript($storeApp, 'upgrade', 'upgrade'));
                $data = array_merge($data, self::getDataForLagoonScript($storeApp, 'post_upgrade', 'post-upgrade'));
                $data = array_merge($data, self::getDataForLagoonScript($storeApp, 'pre_remove', 'pre-remove'));
                $data = array_merge($data, self::getDataForLagoonScript($storeApp, 'remove', 'remove'));
                $data = array_merge($data, self::getDataForLagoonScript($storeApp, 'claim', 'claim'));

                // This is a pre-launch hack for amazee.ai Private GPT
                // TODO: Abstract this once the amazee.ai Private GPT
                //   is launched and stable.
                $data = array_merge($data, AmazeeAiBackendHelper::getDataForPrivateGPTSettings());

                $model->data = $data;
            } catch (PolydockEngineAppNotFoundException $e) {
                Log::error('Failed to set app type for new instance', [
                    'store_app_id' => $model->polydock_store_app_id,
                    'class' => $storeApp->polydock_app_class,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        });

        static::created(function ($appInstance) {
            // Fire the NEW status event if applicable
            if ($appInstance->status === PolydockAppInstanceStatus::NEW) {
                $appInstance->info('MODEL: New app instance created', [
                    'app_instance_id' => $appInstance->id,
                    'status' => $appInstance->status,
                ]);
                event(new PolydockAppInstanceCreatedWithNewStatus($appInstance));
            }
        });

        static::updated(function ($appInstance) {
            if ($appInstance->wasChanged('status')) {
                $appInstance->info('MODEL: Status changed for app instance', [
                    'app_instance_id' => $appInstance->id,
                    'previous_status' => $appInstance->getOriginal('status'),
                    'new_status' => $appInstance->status,
                ]);
                event(new PolydockAppInstanceStatusChanged($appInstance, $appInstance->getOriginal('status')));
            }
        });
    }

    public static function getDataForLagoonScript(PolydockStoreApp $storeApp, string $fieldKeyPart, string $dataKeyPart)
    {
        $data = [];
        $fieldKeyScript = 'lagoon_'.$fieldKeyPart.'_script';
        $dataKeyScript = 'lagoon-'.$dataKeyPart.'-script';

        $fieldKeyService = 'lagoon_'.$fieldKeyPart.'_service';
        $dataKeyService = 'lagoon-'.$dataKeyPart.'-script-service';

        $fieldKeyContainer = 'lagoon_'.$fieldKeyPart.'_container';
        $dataKeyContainer = 'lagoon-'.$dataKeyPart.'-script-container';

        if ($storeApp->{$fieldKeyScript}) {
            $data[$dataKeyScript] = $storeApp->{$fieldKeyScript};

            if ($storeApp->{$fieldKeyService}) {
                $data[$dataKeyService] = $storeApp->{$fieldKeyService};
            } else {
                $data[$dataKeyService] = 'cli';
            }

            if ($storeApp->{$fieldKeyContainer}) {
                $data[$dataKeyContainer] = $storeApp->{$fieldKeyContainer};
            } else {
                $data[$dataKeyContainer] = 'cli';
            }
        }

        Log::info('Data for Lagoon script', [
            'store_app_id' => $storeApp->id,
            'field_key_part' => $fieldKeyPart,
            'data_key_part' => $dataKeyPart,
            'data' => $data,
        ]);

        return $data;
    }

    /**
     * Set the app for the app instance
     *
     * @param  PolydockAppInterface  $app  The app to set
     * @return self Returns the instance for method chaining
     */
    public function setApp(PolydockAppInterface $app): self
    {
        $this->app = $app;

        return $this;
    }

    /**
     * Get the app for the app instance
     *
     * @return PolydockAppInterface The app
     */
    public function getApp(): PolydockAppInterface
    {
        return $this->app;
    }

    /**
     * Set name of app instance
     */
    public function setName(string $name): self
    {
        $this->attributes['name'] = $name;

        return $this;
    }

    /**
     * Get the name of the app instance
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the type of the app instance
     *
     * @param  string  $appType  The type of the app instance
     * @return self Returns the instance for method chaining
     *
     * @throws PolydockEngineAppNotFoundException
     */
    public function setAppType(string $appType): self
    {

        if (! class_exists($appType)) {
            throw new PolydockEngineAppNotFoundException($appType);
        }

        $appType = str_replace('\\', '_', $appType);

        $this->app_type = $appType;

        return $this;
    }

    /**
     * Get the type of the app instance
     *
     * @return string The type of the app instance
     */
    public function getAppType(): string
    {
        return $this->app_type;
    }

    /**
     * Set the status of the app instance
     *
     * @param  PolydockAppInstanceStatus  $status  The new status to set
     * @param  string  $statusMessage  The status message to set
     * @return self Returns the instance for method chaining
     */
    public function setStatus(PolydockAppInstanceStatus $status, string $statusMessage = ''): self
    {
        if ($this->status !== $status) {
            $previousStatus = $this->status;
            $this->status = $status;

            Log::info('Setting status of app instance', [
                'app_instance_id' => $this->id,
                'previous_status' => $previousStatus,
                'new_status' => $status,
            ]);

            // Stamp the grace clock the first time we land on REMOVED.
            if ($status === PolydockAppInstanceStatus::REMOVED && $this->removed_at === null) {
                $now = now();
                $graceDays = (int) config('polydock.cleanup.project_grace_period_days', 14);
                $this->removed_at = $now;
                $this->purge_eligible_at = $now->copy()->addDays($graceDays);
            }

            if (! empty($statusMessage)) {
                $this->setStatusMessage($statusMessage);
            }
        } else {
            $this->info('Setting status of app instance to same status', [
                'app_instance_id' => $this->id,
                'previous_status' => $this->status,
                'status' => $status,
            ]);

            if (! empty($statusMessage)) {
                $this->setStatusMessage($statusMessage);
            }
        }

        return $this;
    }

    /**
     * Get the status of the app instance
     *
     * @return PolydockAppInstanceStatus The current status enum value
     */
    public function getStatus(): PolydockAppInstanceStatus
    {
        return $this->status;
    }

    /**
     * Set the status message of the app instance
     *
     * @param  string  $statusMessage  The status message to set
     * @return self Returns the instance for method chaining
     */
    public function setStatusMessage(string $statusMessage): self
    {
        $this->status_message = $statusMessage;

        return $this;
    }

    /**
     * Eloquent mutator to safely truncate status messages before writing to DB.
     */
    public function setStatusMessageAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['status_message'] = null;

            return;
        }

        $string = (string) $value;
        $maxBytes = 2000; // Safe limit for status messages (approx 500-2000 chars)

        if (strlen($string) > $maxBytes) {
            $this->attributes['status_message'] = mb_strcut($string, 0, $maxBytes - 3, 'UTF-8').'...';
        } else {
            $this->attributes['status_message'] = $string;
        }
    }

    /**
     * Get the status message of the app instance
     *
     * @return string The status message
     */
    public function getStatusMessage(): string
    {
        return $this->status_message;
    }

    /**
     * Keys whose values are encrypted transparently at rest inside the `data`
     * column. Writes go through {@see encryptSecretValue()} in storeKeyValue()
     * and reads are decrypted in getKeyValue(), so callers keep passing/reading
     * plain arrays.
     *
     * @var list<string>
     */
    private const ENCRYPTED_KEYS = [
        'secret',
    ];

    /**
     * Store a key-value pair for the app instance
     *
     * @param  string  $key  The key to store
     * @param  mixed  $value  The value to store
     * @return PolydockAppInstanceInterface Returns the instance for method chaining
     */
    public function storeKeyValue(string $key, $value): PolydockAppInstanceInterface
    {
        if ($key === 'polydock-app-instance-health-webhook-url' && ! empty($value)) {
            $value = $this->stripTokenFromUrl((string) $value);
        }

        if (in_array($key, self::ENCRYPTED_KEYS, true)) {
            $value = $this->encryptSecretValue($value);
        }

        $resultData = $this->data ?? [];
        data_set($resultData, $key, $value);
        $this->data = $resultData;
        $this->save();

        return $this;
    }

    public function getKeyValue(string $key): mixed
    {
        $value = data_get($this->data, $key, '');

        if (in_array($key, self::ENCRYPTED_KEYS, true)) {
            return $this->decryptSecretValue($value);
        }

        if ($key === 'polydock-app-instance-health-webhook-url' && ! empty($value)) {
            $token = config('polydock.health_token');
            if (! empty($token)) {
                $value = $this->stripTokenFromUrl((string) $value);
                $separator = str_contains((string) $value, '?') ? '&' : '?';

                return $value.$separator.'token='.urlencode($token);
            }
        }

        return $value;
    }

    /**
     * Sentinel prefix marking a value as an encrypted-at-rest secret payload.
     * Lets getKeyValue() distinguish already-encrypted ciphertext from any
     * legacy plaintext still present in the `data` column (backfill guard).
     */
    private const ENCRYPTED_SECRET_PREFIX = 'enc:v1:';

    /**
     * Encrypt a secret value for storage inside the `data` column.
     *
     * The value (typically an array of credentials) is serialised and encrypted
     * with Laravel's Crypt (APP_KEY-derived, AES-256-CBC + HMAC). The result is
     * a single opaque, prefixed string so the JSON column holds only ciphertext.
     * Empty values are stored as-is so "no secret" stays cheaply detectable.
     */
    private function encryptSecretValue(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return $value;
        }

        // Idempotent: never double-encrypt an already-encrypted payload.
        if (is_string($value) && str_starts_with($value, self::ENCRYPTED_SECRET_PREFIX)) {
            return $value;
        }

        // JSON_THROW_ON_ERROR: fail loudly on an unencodable secret rather than
        // silently storing 'null' (which would read back as null — quiet data loss).
        return self::ENCRYPTED_SECRET_PREFIX.Crypt::encryptString(json_encode($value, JSON_THROW_ON_ERROR));
    }

    /**
     * Decrypt a secret value read from the `data` column.
     *
     * Ciphertext (prefixed strings) is decrypted and JSON-decoded back to the
     * original shape. Values without the prefix are returned untouched so
     * legacy plaintext rows keep working until they are backfilled.
     */
    private function decryptSecretValue(mixed $value): mixed
    {
        if (! is_string($value) || ! str_starts_with($value, self::ENCRYPTED_SECRET_PREFIX)) {
            // Legacy plaintext (or empty default) — return as stored.
            return $value;
        }

        $ciphertext = substr($value, strlen(self::ENCRYPTED_SECRET_PREFIX));

        try {
            $decrypted = Crypt::decryptString($ciphertext);
        } catch (DecryptException $e) {
            // Most likely APP_KEY was rotated without a re-encrypt pass, or the
            // stored ciphertext is corrupted. Fail safe (null) and log rather
            // than taking down every request/queue job that reads this secret.
            Log::error('Failed to decrypt instance secret', [
                'app_instance_id' => $this->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return json_decode($decrypted, true);
    }

    /**
     * Reconstructs the URL with the 'token' query parameter removed if present.
     */
    private function stripTokenFromUrl(string $url): string
    {
        $parsedUrl = parse_url($url);
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            if (isset($queryParams['token'])) {
                unset($queryParams['token']);
                $queryString = http_build_query($queryParams);
                $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'].'://' : '';
                $host = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
                $port = isset($parsedUrl['port']) ? ':'.$parsedUrl['port'] : '';
                $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';

                return $scheme.$host.$port.$path.($queryString !== '' ? '?'.$queryString : '');
            }
        }

        return $url;
    }

    /**
     * Delete a stored key-value pair
     *
     * @param  string  $key  The key to delete
     * @return self Returns the instance for method chaining
     */
    public function deleteKeyValue(string $key): self
    {
        unset($this->data[$key]);

        return $this;
    }

    public function setLogger(PolydockAppLoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function getLogger(): PolydockAppLoggerInterface
    {
        return $this->logger;
    }

    public function info(string $message, array $context = []): self
    {
        if (isset($this->logger)) {
            $this->logger->info($message, $context);
        }

        $this->logLine('info', $message, $context);

        return $this;
    }

    public function error(string $message, array $context = []): self
    {
        if (isset($this->logger)) {
            $this->logger->error($message, $context);
        }

        $this->logLine('error', $message, $context);

        return $this;
    }

    public function warning(string $message, array $context = []): self
    {
        if (isset($this->logger)) {
            $this->logger->warning($message, $context);
        }

        $this->logLine('warning', $message, $context);

        return $this;
    }

    public function debug(string $message, array $context = []): self
    {
        if (isset($this->logger)) {
            $this->logger->debug($message, $context);
        }

        $this->logLine('debug', $message, $context);

        return $this;
    }

    public function setEngine(PolydockEngineInterface $engine): self
    {
        $this->engine = $engine;

        return $this;
    }

    public function getEngine(): PolydockEngineInterface
    {
        return $this->engine;
    }

    /**
     * Pick a random animal name
     */
    public static function pickAnimal(): string
    {
        $animals = [
            'Lion', 'Tiger', 'Bear', 'Wolf', 'Fox', 'Eagle', 'Hawk', 'Dolphin', 'Whale', 'Elephant',
            'Giraffe', 'Zebra', 'Penguin', 'Kangaroo', 'Koala', 'Panda', 'Gorilla', 'Cheetah', 'Leopard', 'Jaguar',
            'Rhinoceros', 'Hippopotamus', 'Crocodile', 'Alligator', 'Turtle', 'Snake', 'Lizard', 'Iguana', 'Chameleon', 'Gecko',
            'Octopus', 'Squid', 'Jellyfish', 'Starfish', 'Seahorse', 'Shark', 'Stingray', 'Swordfish', 'Tuna', 'Salmon',
            'Owl', 'Parrot', 'Toucan', 'Flamingo', 'Peacock', 'Hummingbird', 'Woodpecker', 'Cardinal', 'Sparrow', 'Robin',
            'Butterfly', 'Dragonfly', 'Ladybug', 'Beetle', 'Ant', 'Spider', 'Scorpion', 'Crab', 'Lobster', 'Shrimp',
            'Deer', 'Moose', 'Elk', 'Bison', 'Buffalo', 'Antelope', 'Gazelle', 'Camel', 'Llama', 'Alpaca',
            'Raccoon', 'Badger', 'Beaver', 'Otter', 'Meerkat', 'Mongoose', 'Weasel', 'Ferret', 'Skunk', 'Armadillo',
            'Sloth', 'Orangutan', 'Chimpanzee', 'Baboon', 'Lemur', 'Gibbon', 'Marmoset', 'Tamarin', 'Capuchin', 'Macaque',
            'Platypus', 'Echidna', 'Opossum', 'Wombat', 'Tasmanian', 'Dingo', 'Quokka', 'Numbat', 'Wallaby', 'Bilby',
        ];

        return str_replace(' ', '', $animals[array_rand($animals)]);
    }

    /**
     * Pick a random verb
     */
    private function pickVerb(): string
    {
        $verbs = [
            'Sleeping', 'Running', 'Jumping', 'Flying', 'Swimming',
            'Dancing', 'Singing', 'Playing', 'Hunting', 'Dreaming',
            'Climbing', 'Diving', 'Soaring', 'Prowling', 'Leaping',
            'Gliding', 'Stalking', 'Bouncing', 'Dashing', 'Floating',
            'Sprinting', 'Hopping', 'Crawling', 'Sliding', 'Swinging',
            'Pouncing', 'Galloping', 'Prancing', 'Skipping', 'Strolling',
            'Wandering', 'Exploring', 'Roaming', 'Meandering', 'Trotting',
            'Charging', 'Lunging', 'Darting', 'Zigzagging', 'Circling',
            'Twirling', 'Spinning', 'Rolling', 'Tumbling', 'Flipping',
            'Stretching', 'Yawning', 'Resting', 'Lounging', 'Relaxing',
        ];

        return $verbs[array_rand($verbs)];
    }

    /**
     * Pick a random color
     */
    public static function pickColor(): string
    {
        $colors = [
            'Red', 'Blue', 'Green', 'Yellow', 'Purple',
            'Orange', 'Silver', 'Gold', 'Crimson', 'Azure',
            'Emerald', 'Amber', 'Violet', 'Coral', 'Indigo',
        ];

        return $colors[array_rand($colors)];
    }

    /**
     * Generate a unique project name using an animal, verb and UUID
     *
     * @param  string  $prefix  The prefix for the project name
     * @return string The generated unique name
     */
    public function generateUniqueProjectName(string $prefix, ?PolydockStoreApp $storeApp = null): string
    {
        $adjectives = $storeApp?->project_naming_adjectives ?: [];
        $nouns = $storeApp?->project_naming_nouns ?: [];

        $adjective = $adjectives === [] ? self::pickColor() : $adjectives[array_rand($adjectives)];
        $noun = $nouns === [] ? self::pickAnimal() : $nouns[array_rand($nouns)];

        return strtolower(
            $prefix.'-'.
            // $this->pickVerb() . '-' . // we're removing the verb for now, it's not necessary
            $adjective.'-'.
            $noun.'-'.
            uniqid()
        );
    }

    /**
     * Get the store app that this instance belongs to
     */
    public function storeApp(): BelongsTo
    {
        return $this->belongsTo(PolydockStoreApp::class, 'polydock_store_app_id');
    }

    /**
     * Get the user group that owns this instance
     */
    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class);
    }

    /**
     * Get the deployment run that most recently redeployed this instance.
     */
    public function deploymentRun(): BelongsTo
    {
        return $this->belongsTo(PolydockDeploymentRun::class, 'deployment_run_id');
    }

    /**
     * Whether this instance is currently in a state that can be redeployed.
     */
    public function isRedeployEligible(): bool
    {
        return in_array($this->status, self::$redeployEligibleStatuses, true);
    }

    /**
     * Whether this instance has a redeploy that has been triggered but not yet
     * observed as finished (used to avoid firing a second deploy over the top).
     */
    public function hasInFlightDeployment(): bool
    {
        return $this->deployment_run_id !== null
            && $this->deploymentRun !== null
            && ! $this->deploymentRun->isTerminal();
    }

    /**
     * Get all variables for this app instance
     */
    public function variables()
    {
        return $this->morphMany(PolydockVariable::class, 'variabled');
    }

    /**
     * Generate a unique password using either color-animal or verb-animal pattern
     *
     * @return string The generated password
     */
    public function generateUniquePassword(): string
    {
        // Randomly choose between color-animal or verb-animal pattern
        if (random_int(0, 1) === 0) {
            return strtolower(self::pickColor().self::pickAnimal());
        } else {
            return strtolower($this->pickVerb().self::pickAnimal());
        }
    }

    /**
     * Pick a random username prefix
     */
    private function pickUsernamePrefix(): string
    {
        $prefixes = [
            'admin',
            'demo',
            'trial',
            'test',
            'user',
            'guest',
            'preview',
        ];

        return $prefixes[array_rand($prefixes)];
    }

    /**
     * Generate a unique username
     *
     * @return string The generated username
     */
    public function generateUniqueUsername(): string
    {
        return strtolower($this->pickUsernamePrefix().random_int(1000, 9999));
    }

    /**
     * Get the logs for this instance
     */
    public function logs(): HasMany
    {
        return $this->hasMany(PolydockAppInstanceLog::class);
    }

    public function logLine(string $level, string $message, array $context = []): self
    {
        $this->logs()->create([
            'type' => 'model_log',
            'level' => $level,
            'message' => $message,
            'data' => $context,
        ]);

        return $this;
    }

    /**
     * Get the remote registration associated with this instance
     */
    public function remoteRegistration(): HasOne
    {
        return $this->hasOne(UserRemoteRegistration::class, 'polydock_app_instance_id');
    }

    // Helper method to check if trial is active
    public function isTrialActive(): bool
    {
        if (! $this->is_trial || $this->trial_completed) {
            return false;
        }

        return $this->trial_ends_at->isFuture();
    }

    // Helper method to check if trial is expired
    public function isTrialExpired(): bool
    {
        if (! $this->is_trial) {
            return false;
        }

        return $this->trial_ends_at->isPast();
    }

    /**
     * Set the one-time login URL with expiration
     *
     * @param  string  $url  The one-time login URL
     * @param  int  $numberOfHours  Number of hours the URL is valid for
     * @param  bool  $setOnlyDontSave  If true, won't save the model
     */
    public function setOneTimeLoginUrl(string $url, int $numberOfHours = 24, bool $setOnlyDontSave = false): self
    {
        $this->app_one_time_login_url = $url;
        $this->app_one_time_login_valid_until = now()->addHours($numberOfHours);

        if (! $setOnlyDontSave) {
            $this->save();
        }

        return $this;
    }

    public function setAppUrl(string $url, ?string $oneTimeLoginUrl = null, ?int $numberOfHoursForOneTimeLoginUrl = 24): self
    {
        $this->app_url = trim($url);
        if ($oneTimeLoginUrl) {
            $this->setOneTimeLoginUrl(trim($oneTimeLoginUrl), $numberOfHoursForOneTimeLoginUrl);
        }

        return $this;
    }

    /**
     * Check if the one-time login URL has expired
     */
    public function oneTimeLoginUrlHasExpired(): bool
    {
        if (! $this->app_one_time_login_valid_until) {
            return true;
        }

        return $this->app_one_time_login_valid_until->isPast();
    }

    /**
     * Get the lagoon generated app admin username
     */
    public function getGeneratedAppAdminUsername(): string
    {
        return $this->getKeyValue('lagoon-generate-app-admin-username') ?? '';
    }

    /**
     * Get the lagoon generated app admin password
     */
    public function getGeneratedAppAdminPassword(): string
    {
        return $this->getKeyValue('lagoon-generate-app-admin-password') ?? '';
    }

    /**
     * Get the user's first name
     */
    public function getUserFirstName(): string
    {
        return $this->getKeyValue('user-first-name') ?? '';
    }

    /**
     * Get the user's last name
     */
    public function getUserLastName(): string
    {
        return $this->getKeyValue('user-last-name') ?? '';
    }

    /**
     * Get the user's email
     */
    public function getUserEmail(): string
    {
        return $this->getKeyValue('user-email') ?? '';
    }

    /**
     * Calculate and set trial dates based on duration
     *
     * @param  int|null  $overrideDurationDays  Override the store app's trial duration
     * @param  bool  $saveModel  Whether to save the model after setting trial dates
     */
    public function calculateAndSetTrialDates(?int $overrideDurationDays = null, ?bool $saveModel = false): self
    {
        // Use override duration if provided, otherwise use store app duration
        $durationDays = $overrideDurationDays ?? $this->storeApp->trial_duration_days;

        if ($durationDays > 0) {
            $this->trial_ends_at = now()->addDays($durationDays);

            if ($this->storeApp->send_midtrial_email) {
                $halfwayPoint = $durationDays / 2;
                $this->send_midtrial_email_at = now()->addDays($halfwayPoint);
                $this->midtrial_email_sent = false;
            }

            if ($this->storeApp->send_one_day_left_email) {
                $oneDayBeforeEnd = $durationDays - 1;
                $this->send_one_day_left_email_at = now()->addDays($oneDayBeforeEnd);
                $this->one_day_left_email_sent = false;
            }

            if ($this->storeApp->send_trial_complete_email) {
                $this->trial_complete_email_sent = false;
            }

            if ($saveModel) {
                $this->save();
            }
        } else {
            $this->trial_ends_at = null;
        }

        return $this;
    }

    /**
     * Calculate and set trial dates based on end datetime
     *
     * @param  \DateTime|Carbon  $trialEndDateTime  When the trial should end
     * @param  bool  $saveModel  Whether to save the model after setting trial dates
     */
    public function calculateAndSetTrialDatesFromEndDate($trialEndDateTime, bool $saveModel = false): self
    {
        // diffInDays() returns a float in Carbon 3; round up so a partial day still
        // grants a full trial day rather than being truncated toward zero.
        $durationDays = (int) ceil((float) now()->diffInDays($trialEndDateTime));

        return $this->calculateAndSetTrialDates($durationDays, $saveModel);
    }
}
