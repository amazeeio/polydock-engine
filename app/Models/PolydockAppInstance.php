<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInterface;
use FreedomtechHosting\PolydockApp\PolydockAppLoggerInterface;
use FreedomtechHosting\PolydockApp\PolydockEngineInterface;

use App\PolydockEngine\PolydockEngineAppNotFoundException;
use App\Traits\HasPolydockVariables;
use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Traits\HasWebhookSensitiveData;

class PolydockAppInstance extends Model implements PolydockAppInstanceInterface
{
    use HasPolydockVariables;
    use HasWebhookSensitiveData;

    /**
     * The fillable attributes for the model
     * @var array
     */
    protected $fillable = [
        'polydock_store_app_id',
        'user_group_id',
        'app_type',
        'status',
        'status_message',
    ];

    /**
     * The casts for the model
     * @var array
     */
    protected $casts = [
        'status' => PolydockAppInstanceStatus::class,
        'data' => 'array',
    ];

    /**
     * The engine for the app instance
     * @var PolydockEngineInterface
     */
    private PolydockEngineInterface $engine;

    /**
     * The app for the app instance
     * @var PolydockAppInterface
     */
    private PolydockAppInterface $app;

    /**
     * The logger for the app instance
     * @var PolydockAppLoggerInterface
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
        
        // Regex patterns
        '/^.*_key$/',              // Anything ending with _key
        '/^.*_secret$/',           // Anything ending with _secret
        '/^.*password.*$/',        // Anything containing password
        '/^.*token.*$/',           // Anything containing token
        '/^.*api[_-]?key.*$/i',    // Any variation of api key
        '/^.*ssh[_-]?key.*$/i',    // Any variation of ssh key
        '/^.*private[_-]?key.*$/i' // Any variation of private key
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
    ];

    public static array $pollingStatuses = [
        PolydockAppInstanceStatus::DEPLOY_RUNNING,
        PolydockAppInstanceStatus::UPGRADE_RUNNING,
        PolydockAppInstanceStatus::RUNNING_HEALTHY,
        PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
        PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE,
    ];

    /**
     * Boot the model.
     */
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

                $model->data = [
                    'lagoon-deploy-git' => $storeApp->lagoon_deploy_git,
                    'lagoon-deploy-branch' => $storeApp->lagoon_deploy_branch,
                    'lagoon-deploy-organization-id' => $storeApp->lagoon_deploy_organization_id,
                    'lagoon-deploy-region-id' => $storeApp->lagoon_deploy_region_id,
                    'lagoon-deploy-private-key' => $storeApp->lagoon_deploy_private_key,
                    'lagoon-deploy-project-prefix' => $storeApp->lagoon_deploy_project_prefix,
                    'lagoon-project-name' => $model->generateUniqueProjectName($storeApp->lagoon_deploy_project_prefix),
                    'amazee-ai-backend-region-id' => $storeApp->amazee_ai_backend_region_id,
                    'available-for-trials' => $storeApp->available_for_trials,
                ];

            } catch (PolydockEngineAppNotFoundException $e) {
                Log::error('Failed to set app type for new instance', [
                    'store_app_id' => $model->polydock_store_app_id,
                    'class' => $storeApp->polydock_app_class,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });

        static::created(function ($appInstance) {
            // Fire the NEW status event if applicable
            if ($appInstance->status === PolydockAppInstanceStatus::NEW) {
                Log::info('MODEL: New app instance created', [
                    'app_instance_id' => $appInstance->id,
                    'status' => $appInstance->status
                ]);
                event(new PolydockAppInstanceCreatedWithNewStatus($appInstance));
            }
        });

        static::updated(function ($appInstance) {
            if ($appInstance->isDirty('status')) {
                Log::info('MODEL: Status changed for app instance', [
                    'app_instance_id' => $appInstance->id,
                    'previous_status' => $appInstance->getOriginal('status'),
                    'new_status' => $appInstance->status
                ]);
                event(new PolydockAppInstanceStatusChanged($appInstance, $appInstance->getOriginal('status')));
            }
        });
    }

    /**
     * Set the app for the app instance
     * @param PolydockAppInterface $app The app to set
     * @return self Returns the instance for method chaining
     */
    public function setApp(PolydockAppInterface $app) : self
    {
        $this->app = $app;
        return $this;
    }

    /**
     * Get the app for the app instance
     * @return PolydockAppInterface The app
     */
    public function getApp() : PolydockAppInterface
    {
        return $this->app;
    }

    /**
     * Set the type of the app instance
     * @param string $appType The type of the app instance
     * @return self Returns the instance for method chaining
     */
    public function setAppType(string $appType) : self
    {

        if(! class_exists($appType)) {
            throw new PolydockEngineAppNotFoundException($appType);
        }
        
        $appType = str_replace("\\", "_", $appType);

        $this->app_type = $appType;

        return $this;
    }

    /**
     * Get the type of the app instance
     * @return string The type of the app instance
     */
    public function getAppType() : string
    {
        return $this->app_type;
    }

    /**
     * Set the status of the app instance
     * @param PolydockAppInstanceStatus $status The new status to set
     * @param string $statusMessage The status message to set
     * @return self Returns the instance for method chaining
     */
    public function setStatus(PolydockAppInstanceStatus $status, string $statusMessage = "") : self
    {
        if ($this->status !== $status) {
            $previousStatus = $this->status;
            $this->status = $status;

            Log::info('Setting status of app instance', [
                'app_instance_id' => $this->id,
                'previous_status' => $previousStatus,
                'new_status' => $status
            ]);

            if(!empty($statusMessage)) {
                $this->setStatusMessage($statusMessage);
            }
        } else {
            Log::info('Setting status of app instance to same status', [
                'app_instance_id' => $this->id,
                'previous_status' => $this->status,
                'status' => $status
            ]);

            if(!empty($statusMessage)) {
                $this->setStatusMessage($statusMessage);
            }
        }

        return $this;
    }

    /**
     * Get the status of the app instance
     * @return PolydockAppInstanceStatus The current status enum value
     */
    public function getStatus() : PolydockAppInstanceStatus
    {
        return $this->status;
    }

    /**
     * Set the status message of the app instance
     * @param string $statusMessage The status message to set
     * @return self Returns the instance for method chaining
     */
    public function setStatusMessage(string $statusMessage) : self
    {
        $this->status_message = $statusMessage;
        return $this;
    }

    /**
     * Get the status message of the app instance
     * @return string The status message
     */ 
    public function getStatusMessage() : string 
    {
        return $this->status_message;
    }
    
    /**
     * Store a key-value pair for the app instance
     * @param string $key The key to store
     * @param string $value The value to store
     * @return self Returns the instance for method chaining
     */
    public function storeKeyValue(string $key, string $value) : self
    {
        $resultData = $this->data ?? [];
        data_set($resultData, $key, $value);
        $this->data = $resultData;
        $this->save();
        return $this;
    }   

    /**
     * Get a stored value by key
     * @param string $key The key to retrieve
     * @return string The stored value, or empty string if not found
     */
    public function getKeyValue(string $key) : string
    {
        return data_get($this->data, $key) ?? "";
    }

    /** 
     * Delete a stored key-value pair
     * @param string $key The key to delete
     * @return self Returns the instance for method chaining
     */
    public function deleteKeyValue(string $key) : self
    {
        unset($this->data[$key]);
        return $this;
    }

    public function setLogger(PolydockAppLoggerInterface $logger) : self
    {
        $this->logger = $logger;
        return $this;
    }

    public function getLogger() : PolydockAppLoggerInterface
    {
        return $this->logger;
    }

    public function info(string $message, array $context = []) : self
    {
        $this->logger->info($message, $context);
        return $this;
    }

    public function error(string $message, array $context = []) : self
    {
        $this->logger->error($message, $context);
        return $this;
    }

    public function warning(string $message, array $context = []) : self
    {
        $this->logger->warning($message, $context);
        return $this;
    }

    public function debug(string $message, array $context = []) : self
    {
        $this->logger->debug($message, $context);
        return $this;
    }

    public function setEngine(PolydockEngineInterface $engine) : self
    {
        $this->engine = $engine;
        return $this;
    }
    
    public function getEngine() : PolydockEngineInterface
    {
        return $this->engine;
    }

    /**
     * Generate a unique project name using an animal, verb and UUID
     * @param string $prefix The prefix for the project name
     * @return string The generated unique name
     */
    public function generateUniqueProjectName(string $prefix) : string 
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
            'Hamster', 'Hedgehog', 'Rabbit', 'Mouse', 'Rat', 'Squirrel', 'Chipmunk', 'Mole', 'Vole', 'Gopher',
            'Falcon', 'Vulture', 'Raven', 'Crow', 'Magpie', 'Pigeon', 'Dove', 'Swan', 'Goose', 'Duck',
            'Seal', 'Walrus', 'Penguin', 'Polar Bear', 'Arctic Fox', 'Narwhal', 'Beluga', 'Orca', 'Puffin', 'Albatross',
            'Manta Ray', 'Barracuda', 'Piranha', 'Clownfish', 'Angelfish', 'Lionfish', 'Moray Eel', 'Seahorse', 'Cuttlefish', 'Nautilus',
            'Praying Mantis', 'Grasshopper', 'Cricket', 'Cicada', 'Firefly', 'Moth', 'Wasp', 'Hornet', 'Bee', 'Caterpillar',
            'Pangolin', 'Anteater', 'Aardvark', 'Tapir', 'Okapi', 'Capybara', 'Peccary', 'Coati', 'Binturong', 'Civet',
            'Mandrill', 'Proboscis', 'Langur', 'Howler', 'Spider Monkey', 'Siamang', 'Tarsier', 'Galago', 'Loris', 'Aye-aye',
            'Dugong', 'Manatee', 'Porpoise', 'Dolphin', 'Pilot Whale', 'Sperm Whale', 'Blue Whale', 'Humpback', 'Right Whale', 'Bowhead',
            'Komodo Dragon', 'Monitor Lizard', 'Bearded Dragon', 'Skink', 'Gila Monster', 'Basilisk', 'Tuatara', 'Thorny Devil', 'Frilled Neck', 'Horned Lizard',
            'Red Panda', 'Sun Bear', 'Spectacled Bear', 'Sloth Bear', 'Moon Bear', 'Grizzly', 'Black Bear', 'Brown Bear', 'Kodiak', 'Cave Bear'
        ];
        
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
            'Stretching', 'Yawning', 'Resting', 'Lounging', 'Relaxing'
        ];

        $colors = [
            'Red', 'Blue', 'Green', 'Yellow', 'Purple', 'Orange', 'Silver', 'Gold'
        ];
        
        $animal = str_replace(' ', '', $animals[array_rand($animals)]);
        $verb = $verbs[array_rand($verbs)];
        $color = $colors[array_rand($colors)];
        return strtolower($prefix . '-' . $verb . '-' . $color . '-' . $animal . '-' . uniqid());
    }
    
    /**
     * Get the store app that this instance belongs to
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function storeApp(): BelongsTo
    {
        return $this->belongsTo(PolydockStoreApp::class, 'polydock_store_app_id');
    }

    /**
     * Get the user group that owns this instance
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
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
}