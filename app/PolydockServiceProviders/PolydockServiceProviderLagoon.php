<?php

namespace App\PolydockServiceProviders;

use amazeeio\PolydockApp\PolydockServiceProviderInterface;
use amazeeio\PolydockApp\PolydockAppLoggerInterface;
use amazeeio\PolydockPhp\Client;
use App\PolydockEngine\PolydockEngineServiceProviderInitializationException;

/**
 * Polydock service provider for the Lagoon client
 */
class PolydockServiceProviderLagoon implements PolydockServiceProviderInterface
{
    /**
     * @var PolydockAppLoggerInterface
     */
    protected PolydockAppLoggerInterface $logger;

    /**
     * @var Client
     */
    protected Client $LagoonClient;

    /** @var int Maximum age in minutes before a token is considered expired */
    const MAX_TOKEN_AGE_MINUTES = 2;

    public function __construct(array $config, PolydockAppLoggerInterface $logger)
    {
        $this->setLogger($logger);

        $this->LagoonClient = new Client($config);

        if(! is_dir($config['token_cache_dir']))
        {
            mkdir($config['token_cache_dir'], 0755, true);
        }

        if(! is_dir($config['token_cache_dir']))
        {
            throw new PolydockEngineServiceProviderInitializationException("token_cache_dir is not created: " . $config['token_cache_dir']);
        }

        if(! isset($config['ssh_private_key_file']))
        {
            throw new PolydockEngineServiceProviderInitializationException("ssh_private_key_file is not set");
        }

        if(! isset($config['debug']))
        {
            $config['debug'] = false;
        }

        if($config['debug'])
        {
            $this->debug("Configuration: ", $config);
        }

        $this->initLagoonClient($config);
    }

    /**
     * Initialize the Lagoon API client
     *
     * Sets up authentication using an SSH key and manages token caching
     *
     * @param string $sshPrivateKeyFile Path to SSH private key file
     */
    protected function initLagoonClient(array $config)
    {
        $debug = $config['debug'] ?? false;

        $sshPrivateKeyFile = $config['ssh_private_key_file'];

        $sshServer = $config['ssh_server'] ?? 'ssh.lagoon.amazeeio.cloud';
        $sshPort = $config['ssh_port'] ?? 32222;
        $endpoint = $config['endpoint'] ?? 'https://api.lagoon.amazeeio.cloud/graphql';
        $sshUser = $config['ssh_user'] ?? 'lagoon';

        $this->LagoonClient = new Client($config);

        $tokenFile = $config['token_cache_dir'] . DIRECTORY_SEPARATOR . md5($sshServer . "-". $sshPrivateKeyFile . "-". $sshUser . "-". $sshPort . "-". $endpoint) . ".token";

        if(file_exists($tokenFile) && !(((time() - filemtime($tokenFile)) / 60) > self::MAX_TOKEN_AGE_MINUTES)) {
            if($debug) {
                $this->debug("Loaded token from: " . $tokenFile);
            }
            $this->LagoonClient->setLagoonToken(file_get_contents($tokenFile));
        } else {
            if($debug) {
                $this->debug("Loading token over SSH");
            }

            $this->LagoonClient->getLagoonTokenOverSsh();

            if($this->LagoonClient->getLagoonToken()) {
                if($debug) {
                    $this->debug("Saved token to: " . $tokenFile);
                }
                file_put_contents($tokenFile, $this->LagoonClient->getLagoonToken());
            } else {
                $this->error("Could not load a Lagoon token");
            }
        }

        $this->LagoonClient->initGraphqlClient();

        if($debug) {
            $whoAmIData = $this->LagoonClient->whoAmI();
            $this->debug("Logged into lagoon: " . json_encode($whoAmIData));
        }
    }

    public function getLagoonClient() : Client
    {
        return $this->LagoonClient;
    }

    public function getMaxTokenAgeMinutes() : int
    {
        return self::MAX_TOKEN_AGE_MINUTES;
    }

    public function getName() : string
    {
        return 'Lagoon-Client-Provider';
    }

    public function getDescription() : string
    {
        return 'An implementation of the Lagoon Client';
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