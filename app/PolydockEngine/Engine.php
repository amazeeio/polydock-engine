<?php

namespace App\PolydockEngine;

use App\PolydockEngine\Traits\PolydockEngineFunctionCallerTrait;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\Exceptions\PolydockEngineProcessPolydockAppInstanceStatusException;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;
use FreedomtechHosting\PolydockApp\PolydockAppLoggerInterface;
use FreedomtechHosting\PolydockApp\PolydockServiceProviderInterface;
use FreedomtechHosting\PolydockApp\PolydockEngineBase;
use FreedomtechHosting\PolydockApp\PolydockEngineInterface;

class Engine extends PolydockEngineBase implements PolydockEngineInterface
{
    use PolydockEngineFunctionCallerTrait;
    
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
     * Process the polydock app instance
     * @param PolydockAppInstanceInterface $appInstance The app instance to process
     * @return PolydockAppInstanceInterface The app instance
     */
    public function processPolydockAppInstance(PolydockAppInstanceInterface $appInstance)
    {
        $appInstance->setLogger($this->logger);
        $appInstance->setEngine($this);

        $polydockAppClass = $appInstance->storeApp->polydock_app_class;
        if(!class_exists($polydockAppClass)) {
            throw new PolydockEngineAppNotFoundException('Class ' . $polydockAppClass . ' not found');
        }
        
        $app = new $polydockAppClass(
            $appInstance->storeApp->name, 
            $appInstance->storeApp->description, 
            $appInstance->storeApp->author, 
            $appInstance->storeApp->website, 
            $appInstance->storeApp->support_email, 
        );

        $app->setLogger($this->logger);

        $this->info("App Name: " . $app->getAppName());
        $this->info("App Description: " . $app->getAppDescription());
        $this->info("App Author: " . $app->getAppAuthor());
        $this->info("App Website: " . $app->getAppWebsite());
        $this->info("App Support Email: " . $app->getAppSupportEmail());
        $appInstance->setApp($app);

        $this->info('Validating app instance has all required variables');

        // Throws PolydockEngineAppInstanceValidationException
        $this->validateAppInstanceHasAllRequiredVariables($appInstance);
        $this->info('App instance has all required variables');

        $stepReturn = false;
        switch($appInstance->getStatus()) {
            case PolydockAppInstanceStatus::PENDING_PRE_CREATE:
                $stepReturn = $this->processPolydockAppUsingFunction($appInstance, 'preCreateAppInstance', 
                    PolydockAppInstanceStatus::PENDING_PRE_CREATE, 
                    PolydockAppInstanceStatus::PRE_CREATE_COMPLETED, 
                    PolydockAppInstanceStatus::PRE_CREATE_FAILED);
                break;
            case PolydockAppInstanceStatus::PENDING_CREATE:
                $stepReturn = $this->processPolydockAppUsingFunction($appInstance, 'createAppInstance', 
                    PolydockAppInstanceStatus::PENDING_CREATE, 
                    PolydockAppInstanceStatus::CREATE_COMPLETED, 
                    PolydockAppInstanceStatus::CREATE_FAILED);
                break;
            case PolydockAppInstanceStatus::PENDING_POST_CREATE:
                $stepReturn = $this->processPolydockAppUsingFunction($appInstance, 'postCreateAppInstance', 
                    PolydockAppInstanceStatus::PENDING_POST_CREATE, 
                    PolydockAppInstanceStatus::POST_CREATE_COMPLETED, 
                    PolydockAppInstanceStatus::POST_CREATE_FAILED);
                break;  
            case PolydockAppInstanceStatus::PENDING_PRE_DEPLOY:
                $stepReturn = $this->processPolydockAppUsingFunction($appInstance, 'preDeployAppInstance', 
                    PolydockAppInstanceStatus::PENDING_PRE_DEPLOY, 
                    PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED, 
                    PolydockAppInstanceStatus::PRE_DEPLOY_FAILED);
                break;
            case PolydockAppInstanceStatus::PENDING_DEPLOY:
                $stepReturn = $this->processPolydockAppUsingFunction($appInstance, 'deployAppInstance', 
                    PolydockAppInstanceStatus::PENDING_DEPLOY, 
                    PolydockAppInstanceStatus::DEPLOY_RUNNING, // Note: This is not a completed status
                    PolydockAppInstanceStatus::DEPLOY_FAILED);
                break;
            case PolydockAppInstanceStatus::PENDING_POST_DEPLOY:    
                $stepReturn = $this->processPolydockAppUsingFunction($appInstance, 'postDeployAppInstance', 
                    PolydockAppInstanceStatus::PENDING_POST_DEPLOY, 
                    PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED, 
                    PolydockAppInstanceStatus::POST_DEPLOY_FAILED);
                break;
            case PolydockAppInstanceStatus::PENDING_PRE_REMOVE:
                $stepReturn = $this->processPolydockAppUsingFunction($appInstance, 'preRemoveAppInstance', 
                    PolydockAppInstanceStatus::PENDING_PRE_REMOVE, 
                    PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED, 
                    PolydockAppInstanceStatus::PRE_REMOVE_FAILED);
                break;  
            case PolydockAppInstanceStatus::PENDING_REMOVE:
                $stepReturn = $this->processPolydockAppUsingFunction($appInstance, 'removeAppInstance', 
                    PolydockAppInstanceStatus::PENDING_REMOVE, 
                    PolydockAppInstanceStatus::REMOVE_COMPLETED, 
                    PolydockAppInstanceStatus::REMOVE_FAILED);
                break;  
            case PolydockAppInstanceStatus::PENDING_POST_REMOVE:
                $stepReturn = $this->processPolydockAppUsingFunction($appInstance, 'postRemoveAppInstance', 
                    PolydockAppInstanceStatus::PENDING_POST_REMOVE, 
                    PolydockAppInstanceStatus::POST_REMOVE_COMPLETED, 
                    PolydockAppInstanceStatus::POST_REMOVE_FAILED);
                break;
            case PolydockAppInstanceStatus::PENDING_PRE_UPGRADE:
                $stepReturn = $this->processPolydockAppUsingFunction($appInstance, 'preUpgradeAppInstance', 
                    PolydockAppInstanceStatus::PENDING_PRE_UPGRADE, 
                    PolydockAppInstanceStatus::PRE_UPGRADE_COMPLETED, 
                    PolydockAppInstanceStatus::PRE_UPGRADE_FAILED);
                break;
            case PolydockAppInstanceStatus::PENDING_UPGRADE:
                $stepReturn = $this->processPolydockAppUsingFunction($appInstance, 'upgradeAppInstance', 
                    PolydockAppInstanceStatus::PENDING_UPGRADE, 
                    PolydockAppInstanceStatus::UPGRADE_RUNNING, // Note: This is not a completed status 
                    PolydockAppInstanceStatus::UPGRADE_FAILED);
                break;
            case PolydockAppInstanceStatus::PENDING_POST_UPGRADE:   
                $stepReturn = $this->processPolydockAppUsingFunction($appInstance, 'postUpgradeAppInstance', 
                    PolydockAppInstanceStatus::PENDING_POST_UPGRADE, 
                    PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED, 
                    PolydockAppInstanceStatus::POST_UPGRADE_FAILED);
                break;
            case PolydockAppInstanceStatus::DEPLOY_RUNNING:
                $stepReturn = $this->processPolydockAppPollUpdateUsingFunction($appInstance, 'pollAppInstanceDeploymentProgress', 
                    PolydockAppInstanceStatus::DEPLOY_RUNNING, 
                    [
                        PolydockAppInstanceStatus::DEPLOY_RUNNING,
                        PolydockAppInstanceStatus::DEPLOY_COMPLETED, 
                        PolydockAppInstanceStatus::DEPLOY_FAILED
                    ]
                );
                break;
            case PolydockAppInstanceStatus::UPGRADE_RUNNING:
                $stepReturn = $this->processPolydockAppPollUpdateUsingFunction($appInstance, 'pollAppInstanceUpgradeProgress', 
                    PolydockAppInstanceStatus::UPGRADE_RUNNING, 
                    [
                        PolydockAppInstanceStatus::UPGRADE_RUNNING,
                        PolydockAppInstanceStatus::UPGRADE_COMPLETED, 
                        PolydockAppInstanceStatus::UPGRADE_FAILED
                    ]
                );
                break;
            case PolydockAppInstanceStatus::RUNNING_HEALTHY:
                $stepReturn = $this->processPolydockAppPollUpdateUsingFunction($appInstance, 'pollAppInstanceHealthStatus', 
                    PolydockAppInstanceStatus::RUNNING_HEALTHY, 
                    [
                        PolydockAppInstanceStatus::RUNNING_HEALTHY,
                        PolydockAppInstanceStatus::RUNNING_UNHEALTHY,
                        PolydockAppInstanceStatus::RUNNING_UNRESPONSIVE
                    ]
                );
                break;
            default:
                $stepReturn = false;
                throw new PolydockAppInstanceStatusFlowException('Status ' 
                    . $appInstance->getStatus()->value 
                    . ' is not a status the engine can process');
        } 

        if(!$stepReturn) {   
            $this->info('Unsuccessful processPolydockAppInstance run - app instance status is now: ' . $appInstance->getStatus()->value);
            throw new PolydockAppInstanceStatusFlowException('Run failed. Status is now ' . $appInstance->getStatus()->value);
        }

        $this->info('Successful processPolydockAppInstance run - app instance status is now: ' . $appInstance->getStatus()->value);

        return $appInstance;
    }

    /**
     * Require the polydock app instance status
     * @param PolydockAppInstanceStatus $status The status to require
     * @throws PolydockEngineProcessPolydockAppInstanceStatusException
     * @return void
     */
    protected function requirePolydockAppInstanceStatus(PolydockAppInstanceStatus $status, PolydockAppInstanceInterface $appInstance) : void
    {
        if($appInstance->getStatus() !== $status) {
            throw new PolydockAppInstanceStatusFlowException(
                'PolydockAppInstance status expected to be ' 
                    . $status->value . ' but is ' . $appInstance->getStatus()->value
            );
        }
    }

    /** 
     * Require the polydock app instance status to be one of a list of statuses
     * @param array<PolydockAppInstanceStatus> $statuses The statuses to require
     * @throws PolydockAppInstanceStatusFlowException
     * @return void
     */
    protected function requirePolydockAppInstanceStatusOneOfList(array $statuses, PolydockAppInstanceInterface $appInstance) : void
    {
        if(!in_array($appInstance->getStatus(), $statuses)) {
            throw new PolydockAppInstanceStatusFlowException(
                'PolydockAppInstance status expected to be one of ' 
                    . implode(', ', array_map(fn($status) => $status->value, $statuses)) 
                    . ' but is ' . $appInstance->getStatus()->value
            );
        }
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
}
