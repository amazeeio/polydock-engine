<?php

namespace App\PolydockServiceProviders;

use amazeeio\PolydockApp\PolydockServiceProviderInterface;
use amazeeio\PolydockApp\PolydockAppLoggerInterface;
use App\PolydockEngine\PolydockEngineServiceProviderInitializationException;
use amazeeio\PolydockAmazeeAIBackendClient\Client;

/**
 * Polydock service provider for the Amazee AI Backend client
 */
class PolydockServiceProviderAmazeeAiBackend implements PolydockServiceProviderInterface
{
    /**
     * @var PolydockAppLoggerInterface
     */
    protected PolydockAppLoggerInterface $logger;

    /**
     * @var Client
     */
    protected Client $AmazeeAiBackendClient;

    public function __construct(array $config, PolydockAppLoggerInterface $logger)
    {
        $this->setLogger($logger);

        $baseUrl = $config['base_url'] ?? null;
        $tokenFile = $config['token_file'] ?? null;

        if(! $baseUrl) {
            throw new PolydockEngineServiceProviderInitializationException("amazee_ai_backend.base_url is not set");
        }

        if(! $tokenFile) {
            throw new PolydockEngineServiceProviderInitializationException("amazee_ai_backend.token_file is not set");
        }

        if(! file_exists($tokenFile)) {
            throw new PolydockEngineServiceProviderInitializationException("amazee_ai_backend.token_file does not exist: " . $tokenFile);
        }

        $token = trim(file_get_contents($tokenFile));

        $this->AmazeeAiBackendClient = new Client($baseUrl, $token);

        if(! isset($config['debug']))
        {
            $config['debug'] = false;
        }

        if($config['debug'])
        {
            $this->debug("Configuration: ", $config);
        }

        $this->initAmazeeAiBackendClient($config);
    }

    /**
     * Initialize the Amazee AI Backend API client
     *
     * Sets up authentication using a token and manages token caching
     *
     * @param array $config The configuration array
     */
    protected function initAmazeeAiBackendClient(array $config)
    {
        $debug = $config['debug'] ?? false;
        $baseUrl = $config['base_url'] ?? null;
        $tokenFile = $config['token_file'] ?? null;

        if(! $baseUrl) {
            throw new PolydockEngineServiceProviderInitializationException("amazee_ai_backend.base_url is not set");
        }

        if(! $tokenFile) {
            throw new PolydockEngineServiceProviderInitializationException("amazee_ai_backend.token_file is not set");
        }

        if(! file_exists($tokenFile)) {
            throw new PolydockEngineServiceProviderInitializationException("amazee_ai_backend.token_file does not exist: " . $tokenFile);
        }

        $token = trim(file_get_contents($tokenFile));
        $this->AmazeeAiBackendClient = new Client($baseUrl, $token);

        if($debug) {
            $whoAmIData = $this->AmazeeAiBackendClient->getMe();
            $this->debug("Logged into amazee ai backend: " . json_encode($whoAmIData));
        }
    }

    public function getAmazeeAiBackendClient() : Client
    {
        return $this->AmazeeAiBackendClient;
    }

    public function getName() : string
    {
        return 'Polydock-Amazee-AI-Backend-Client-Provider';
    }

    public function getDescription() : string
    {
        return 'An implementation of the Polydock Amazee AI Backend Client from polydock-amazeeai-backend-client-php';
    }

    public function getLogger() : PolydockAppLoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(PolydockAppLoggerInterface $logger) : self
    {
        $this->logger = $logger;
        return $this;
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
}