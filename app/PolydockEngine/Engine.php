<?php

namespace App\PolydockEngine;

use App\Models\PolydockAppInstance;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppLoggerInterface;
use FreedomtechHosting\PolydockApp\PolydockServiceProviderInterface;
use FreedomtechHosting\PolydockApp\PolydockEngineBase;
use FreedomtechHosting\PolydockApp\PolydockEngineInterface;

class Engine extends PolydockEngineBase implements PolydockEngineInterface
{
    /**
     * @var PolydockAppInstanceInterface
     */
    private PolydockAppInstanceInterface $appInstance;

    /**
     * @var PolydockAppLoggerInterface
     */
    protected PolydockAppLoggerInterface $logger;

    /**
     * @var array<string, PolydockServiceProviderInterface>
     */
    protected array $polydockServiceProviderSingletonInstances = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $polydockServiceProviderSingletonConfig = [];

    /**
     * Constructor
     * @param PolydockAppLoggerInterface $logger The logger to set
     * @param array<string, array<string, mixed>> $serviceProviderSingletonConfig The config for the polydock service providers
     */
    public function __construct(PolydockAppLoggerInterface $logger, $serviceProviderSingletonConfig = [])
    {

        if(count($serviceProviderSingletonConfig) > 0) {
            $this->polydockServiceProviderSingletonConfig = $serviceProviderSingletonConfig;
        } else {
            $this->polydockServiceProviderSingletonConfig = config('polydock.service_providers_singletons');
        }

        $this->logger = $logger;
        $this->polydockServiceProviderSingletonInstances = [];
        $this->initializePolydockServiceProviders($this->polydockServiceProviderSingletonConfig);
    }

    /**
     * Set the logger for the engine
     * @param PolydockAppLoggerInterface $logger The logger to set
     * @return self Returns the instance for method chaining
     */
    public function setLogger(PolydockAppLoggerInterface $logger) : self
    {
        $this->logger = $logger;
        return $this;
    }   

    /**
     * Get the logger for the engine
     * @return PolydockAppLoggerInterface The logger
     */
    public function getLogger() : PolydockAppLoggerInterface
    {
        return $this->logger;
    }

    /**
     * Initialize the polydock service providers
     * @param array<string, array<string, mixed>> $config The config for the polydock service providers
     * @return self Returns the instance for method chaining
     */ 
    public function initializePolydockServiceProviders(array $config) : self
    {
        $this->info('Initializing polydock service providers');
        foreach($config as $polydockServiceProviderKey => $polydockServiceProviderConfig) {
            $polydockServiceProviderClass = $polydockServiceProviderConfig['class'];
            
            $this->info('Initializing polydock service provider ', ['polydockServiceProviderClass' => $polydockServiceProviderClass]);
            
            if(! class_exists($polydockServiceProviderClass)) {
                throw new PolydockEngineServiceProviderNotFoundException($polydockServiceProviderClass);
            }
    
            $provider = new $polydockServiceProviderClass($polydockServiceProviderConfig, $this->getLogger()); 
    
            if(! $provider instanceof PolydockServiceProviderInterface) { 
                throw new PolydockEngineServiceProviderNotFoundException($polydockServiceProviderClass);   
            }

            $this->polydockServiceProviderSingletonInstances[$polydockServiceProviderKey] = $provider;
        }
        return $this;
    }

    /**
     * Get a polydock service provider singleton instance
     * @param string $polydockServiceProviderClass The class name of the polydock service provider
     * @return PolydockServiceProviderInterface The polydock service provider instance
     */
    public function getPolydockServiceProviderSingletonInstance(string $polydockServiceProviderClass) : PolydockServiceProviderInterface
    {    
        if(! isset($this->polydockServiceProviderSingletonInstances[$polydockServiceProviderClass])) {
            throw new PolydockEngineServiceProviderNotFoundException($polydockServiceProviderClass);
        }

        return $this->polydockServiceProviderSingletonInstances[$polydockServiceProviderClass];
    }

    /**
     * Run a test for the app instance
     * @param string $polydockAppClass The class name of the app instance
     * @return void
     */
    public function run(PolydockAppInstance $appInstance)
    {
        $this->appInstance = $appInstance;
        $this->appInstance->setLogger($this->logger);
        $this->appInstance->setEngine($this);

        $polydockAppClass = $this->appInstance->storeApp->polydock_app_class;
        if(!class_exists($polydockAppClass)) {
            throw new PolydockEngineAppNotFoundException('Class ' . $polydockAppClass . ' not found');
        }
        
        $app = new $polydockAppClass(
            $this->appInstance->storeApp->name, 
            $this->appInstance->storeApp->description, 
            $this->appInstance->storeApp->author, 
            $this->appInstance->storeApp->website, 
            $this->appInstance->storeApp->support_email, 
        );

        $app->setLogger($this->logger);

        $this->info("App Name: " . $app->getAppName());
        $this->info("App Description: " . $app->getAppDescription());
        $this->info("App Author: " . $app->getAppAuthor());
        $this->info("App Website: " . $app->getAppWebsite());
        $this->info("App Support Email: " . $app->getAppSupportEmail());
        $this->appInstance->setApp($app);

        $this->info('Validating app instance has all required variables');
        // Throws PolydockEngineAppInstanceValidationException
        $this->validateAppInstanceHasAllRequiredVariables($appInstance);
        $this->info('App instance has all required variables');

        $this->info('Run has completed. Status is now: ' . $this->appInstance->getStatusMessage());

        return $this->appInstance;
    }

    /**
     * Log an info message
     * @param string $message The message to log
     * @param array<string, mixed> $context The context for the message
     * @return self Returns the instance for method chaining
     */
    public function info(string $message, array $context = []) : self
    {
        $this->logger->info($message, $context);
        return $this;
    }

    /**
     * Log an error message
     * @param string $message The message to log
     * @param array<string, mixed> $context The context for the message
     * @return self Returns the instance for method chaining
     */
    public function error(string $message, array $context = []) : self
    {
        $this->logger->error($message, $context);
        return $this;
    }

    /**
     * Log a warning message
     * @param string $message The message to log
     * @param array<string, mixed> $context The context for the message
     * @return self Returns the instance for method chaining
     */ 
    public function warning(string $message, array $context = []) : self
    {
        $this->logger->warning($message, $context);
        return $this;
    }

    /**
     * Log a debug message
     * @param string $message The message to log
     * @param array<string, mixed> $context The context for the message
     * @return self Returns the instance for method chaining
     */  
    public function debug(string $message, array $context = []) : self
    {
        $this->logger->debug($message, $context);
        return $this;
    }   

    /**
     * Run the pre-create step
     * @return void
     */
    public function runPreCreate()
    {
        $this->appInstance->setStatus(PolydockAppInstanceStatus::PENDING_PRE_CREATE);
        $this->info('Calling pre-create', ['engine' => self::class, 'location' => 'runPreCreate']);
        $this->appInstance->getApp()->preCreateAppInstance($this->appInstance);
    }

    /** 
     * Run the create step
     * @return void
     */
    public function runCreate()
    {
        $this->appInstance->setStatus(PolydockAppInstanceStatus::PENDING_CREATE);
        $this->info('Calling create', ['engine' => self::class, 'location' => 'runCreate']);
        $this->appInstance->getApp()->createAppInstance($this->appInstance);
    }

    /**
     * Run the post-create step
     * @return void
     */
    public function runPostCreate()
    {
        $this->appInstance->setStatus(PolydockAppInstanceStatus::PENDING_POST_CREATE);
        $this->info('Calling post-create', ['engine' => self::class, 'location' => 'runPostCreate']);
        $this->appInstance->getApp()->postCreateAppInstance($this->appInstance);
    }

    /**
     * Run the pre-deploy step
     * @return void
     */
    public function runPreDeploy()
    {
        $this->appInstance->setStatus(PolydockAppInstanceStatus::PENDING_PRE_DEPLOY);
        $this->info('Calling pre-deploy', ['engine' => self::class, 'location' => 'runPreDeploy']);
        $this->appInstance->getApp()->preDeployAppInstance($this->appInstance);
    }

    /**
     * Run the deploy step
     * @return void
     */
    public function runDeploy()
    {
        $this->appInstance->setStatus(PolydockAppInstanceStatus::PENDING_DEPLOY);
        $this->info('Calling deploy', ['engine' => self::class, 'location' => 'runDeploy']);
        $this->appInstance->getApp()->deployAppInstance($this->appInstance);
    }
    
    /**
     * Run the post-deploy step
     * @return void
     */
    public function runPostDeploy()
    {
        $this->appInstance->setStatus(PolydockAppInstanceStatus::PENDING_POST_DEPLOY);
        $this->info('Calling post-deploy', ['engine' => self::class, 'location' => 'runPostDeploy']);
        $this->appInstance->getApp()->postDeployAppInstance($this->appInstance);
    }

    /**
     * Run the pre-remove step
     * @return void
     */
    public function runPreRemove()
    {
        $this->appInstance->setStatus(PolydockAppInstanceStatus::PENDING_PRE_REMOVE);
        $this->info('Calling pre-remove', ['engine' => self::class, 'location' => 'runPreRemove']);
        $this->appInstance->getApp()->preRemoveAppInstance($this->appInstance);
    }

    /**
     * Run the remove step
     * @return void
     */
    public function runRemove()
    {
        $this->appInstance->setStatus(PolydockAppInstanceStatus::PENDING_REMOVE);
        $this->info('Calling remove', ['engine' => self::class, 'location' => 'runRemove']);
        $this->appInstance->getApp()->removeAppInstance($this->appInstance);
    }

    /**
     * Run the post-remove step
     * @return void
     */
    public function runPostRemove()
    {
        $this->appInstance->setStatus(PolydockAppInstanceStatus::PENDING_POST_REMOVE);
        $this->info('Calling post-remove', ['engine' => self::class, 'location' => 'runPostRemove']);
        $this->appInstance->getApp()->postRemoveAppInstance($this->appInstance);
    }
    
}
