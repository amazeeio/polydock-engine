<?php

namespace App\PolydockServiceProviders;

use App\PolydockEngine\PolydockEngineServiceProviderInitializationException;
use FreedomtechHosting\PolydockAmazeeAIBackendClient\Client;
use FreedomtechHosting\PolydockApp\PolydockAppLoggerInterface;
use FreedomtechHosting\PolydockApp\PolydockServiceProviderInterface;

/**
 * Polydock service provider for the Amazee AI Backend client
 */
class PolydockServiceProviderAmazeeAiBackend implements PolydockServiceProviderInterface
{
    protected PolydockAppLoggerInterface $logger;

    protected Client $AmazeeAiBackendClient;

    /**
     * Construct a new service provider amazee.ai backend.
     *
     * @throws PolydockEngineServiceProviderInitializationException
     */
    public function __construct(array $config, PolydockAppLoggerInterface $logger)
    {
        $this->setLogger($logger);

        $baseUrl = $config['base_url'] ?? null;
        $tokenFile = $config['token_file'] ?? null;

        if (! $baseUrl) {
            throw new PolydockEngineServiceProviderInitializationException('amazee_ai_backend.base_url is not set');
        }

        if (! $tokenFile) {
            throw new PolydockEngineServiceProviderInitializationException('amazee_ai_backend.token_file is not set');
        }

        if (! file_exists($tokenFile)) {
            throw new PolydockEngineServiceProviderInitializationException('amazee_ai_backend.token_file does not exist: '
            .$tokenFile);
        }

        $token = trim(file_get_contents($tokenFile));

        $this->AmazeeAiBackendClient = new Client($baseUrl, $token);

        if (! isset($config['debug'])) {
            $config['debug'] = false;
        }

        if ($config['debug']) {
            $this->debug('Configuration: ', $config);
        }

        $this->initAmazeeAiBackendClient($config);
    }

    /**
     * Initialize the Amazee AI Backend API client
     *
     * Sets up authentication using a token and manages token caching
     *
     * @param  array  $config  The configuration array
     */
    protected function initAmazeeAiBackendClient(array $config)
    {
        $debug = $config['debug'] ?? false;
        $baseUrl = $config['base_url'] ?? null;
        $tokenFile = $config['token_file'] ?? null;

        if (! $baseUrl) {
            throw new PolydockEngineServiceProviderInitializationException('amazee_ai_backend.base_url is not set');
        }

        if (! $tokenFile) {
            throw new PolydockEngineServiceProviderInitializationException('amazee_ai_backend.token_file is not set');
        }

        if (! file_exists($tokenFile)) {
            throw new PolydockEngineServiceProviderInitializationException('amazee_ai_backend.token_file does not exist: '
            .$tokenFile);
        }

        $token = trim(file_get_contents($tokenFile));
        $this->AmazeeAiBackendClient = new Client($baseUrl, $token);

        if ($debug) {
            $whoAmIData = $this->AmazeeAiBackendClient->getMe();
            $this->debug('Logged into amazee ai backend: '.json_encode($whoAmIData));
        }
    }

    /**
     * Get the amazee.ai backend client.
     */
    public function getAmazeeAiBackendClient(): Client
    {
        return $this->AmazeeAiBackendClient;
    }

    /**
     * Fixed name for this provider.
     */
    public function getName(): string
    {
        return 'Polydock-Amazee-AI-Backend-Client-Provider';
    }

    /**
     * Fixed description for this provider.
     */
    public function getDescription(): string
    {
        return 'An implementation of the Polydock Amazee AI Backend Client from polydock-amazeeai-backend-client-php';
    }

    /**
     * Get the logger instance.
     */
    public function getLogger(): PolydockAppLoggerInterface
    {
        return $this->logger;
    }

    /**
     * Set the logger instance. Return self for chaining.
     */
    public function setLogger(PolydockAppLoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Send a message marked as info level to the logger.
     */
    public function info(string $message, array $context = []): self
    {
        $this->logger->info($message, $context);

        return $this;
    }

    /**
     * Send a message marked as error level to the logger.
     */
    public function error(string $message, array $context = []): self
    {
        $this->logger->error($message, $context);

        return $this;
    }

    /**
     * Send a message marked as warning level to the logger.
     */
    public function warning(string $message, array $context = []): self
    {
        $this->logger->warning($message, $context);

        return $this;
    }

    /**
     * Send a message marked as debug level to the logger.
     */
    public function debug(string $message, array $context = []): self
    {
        $this->logger->debug($message, $context);

        return $this;
    }
}
