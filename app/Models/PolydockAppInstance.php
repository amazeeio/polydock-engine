<?php

namespace App\Models;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\PolydockEngine\Helpers\AmazeeAiBackendHelper;
use App\PolydockEngine\PolydockEngineAppNotFoundException;
use App\Traits\HasPolydockVariables;
use App\Traits\HasWebhookSensitiveData;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInterface;
use FreedomtechHosting\PolydockApp\PolydockAppLoggerInterface;
use FreedomtechHosting\PolydockApp\PolydockEngineInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PolydockAppInstance extends Model implements PolydockAppInstanceInterface
{
    use HasPolydockVariables;
    use HasWebhookSensitiveData;

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

                $model->name = $model->generateUniqueProjectName($storeApp->lagoon_deploy_project_prefix);

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
            if ($appInstance->isDirty('status')) {
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
     * Set the type of the app instance
     *
     * @param  string  $appType  The type of the app instance
     * @return self Returns the instance for method chaining
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
     * Get the status message of the app instance
     *
     * @return string The status message
     */
    public function getStatusMessage(): string
    {
        return $this->status_message;
    }

    /**
     * Store a key-value pair for the app instance
     *
     * @param  string  $key  The key to store
     * @param  string  $value  The value to store
     * @return self Returns the instance for method chaining
     */
    public function storeKeyValue(string $key, string $value): self
    {
        $resultData = $this->data ?? [];
        data_set($resultData, $key, $value);
        $this->data = $resultData;
        $this->save();

        return $this;
    }

    /**
     * Get a stored value by key
     *
     * @param  string  $key  The key to retrieve
     * @return string The stored value, or empty string if not found
     */
    public function getKeyValue(string $key): string
    {
        return data_get($this->data, $key) ?? '';
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
    private function pickAnimal(): string
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
    private function pickColor(): string
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
    public function generateUniqueProjectName(string $prefix): string
    {
        return strtolower(
            $prefix.'-'.
            // $this->pickVerb() . '-' . // we're removing the verb for now, it's not necessary
            $this->pickColor().'-'.
            $this->pickAnimal().'-'.
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
            return strtolower($this->pickColor().$this->pickAnimal());
        } else {
            return strtolower($this->pickVerb().$this->pickAnimal());
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
     * @param  \DateTime|\Carbon\Carbon  $trialEndDateTime  When the trial should end
     * @param  bool  $saveModel  Whether to save the model after setting trial dates
     */
    public function calculateAndSetTrialDatesFromEndDate($trialEndDateTime, bool $saveModel = false): self
    {
        // Calculate days between now and end date
        $durationDays = now()->diffInDays($trialEndDateTime);

        return $this->calculateAndSetTrialDates($durationDays, $saveModel);
    }
}
