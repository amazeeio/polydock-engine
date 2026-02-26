<?php

namespace App\PolydockServiceProviders;

use App\PolydockEngine\PolydockEngineServiceProviderInitializationException;
use FreedomtechHosting\FtLagoonPhp\Client;
use FreedomtechHosting\PolydockApp\PolydockAppLoggerInterface;
use FreedomtechHosting\PolydockApp\PolydockServiceProviderInterface;

/**
 * Polydock service provider for the FT Lagoon client
 */
class PolydockServiceProviderFTLagoon implements PolydockServiceProviderInterface
{
    protected PolydockAppLoggerInterface $logger;

    protected Client $LagoonClient;

    /**
     * Maximum age in minutes before a token is considered expired.
     *
     * @var int
     */
    private const int MAX_TOKEN_AGE_MINUTES = 2;

    public function __construct(array $config, PolydockAppLoggerInterface $logger)
    {
        $this->setLogger($logger);

        $this->LagoonClient = new Client($config);

        if (! is_dir($config['token_cache_dir'])) {
            mkdir($config['token_cache_dir'], 0755, true);
        }

        if (! is_dir($config['token_cache_dir'])) {
            throw new PolydockEngineServiceProviderInitializationException(
                'token_cache_dir is not created: '.$config['token_cache_dir'],
            );
        }

        if (! isset($config['ssh_private_key_file'])) {
            throw new PolydockEngineServiceProviderInitializationException('ssh_private_key_file is not set');
        }

        if (! isset($config['debug'])) {
            $config['debug'] = false;
        }

        if ($config['debug']) {
            $this->debug('Configuration: ', $config);
        }

        $this->initLagoonClient($config);
    }

    /**
     * Initialize the Lagoon API client
     *
     * Sets up authentication using an SSH key and manages token caching
     *
     * @param  string  $sshPrivateKeyFile  Path to SSH private key file
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

        $tokenFile =
            $config['token_cache_dir']
            .DIRECTORY_SEPARATOR
            .md5($sshServer.'-'.$sshPrivateKeyFile.'-'.$sshUser.'-'.$sshPort.'-'.$endpoint)
            .'.token';

        if (file_exists($tokenFile) && ! (((time() - filemtime($tokenFile)) / 60) > self::MAX_TOKEN_AGE_MINUTES)) {
            if ($debug) {
                $this->debug('Loaded token from: '.$tokenFile);
            }
            $this->LagoonClient->setLagoonToken(file_get_contents($tokenFile));
        } else {
            if ($debug) {
                $this->debug('Loading token over SSH');
            }

            $this->LagoonClient->getLagoonTokenOverSsh();

            $token = $this->LagoonClient->getLagoonToken();
            if ($token) {
                if ($debug) {
                    $this->debug('Saved token to: '.$tokenFile);
                }
                file_put_contents($tokenFile, $token);
            } else {
                $this->error('Could not load a Lagoon token - SSH token fetch returned empty');
            }
        }

        $token = $this->LagoonClient->getLagoonToken();
        if (empty($token)) {
            // Log more details for debugging
            $this->error('Token debug info', [
                'ssh_server' => $config['ssh_server'] ?? 'not set',
                'ssh_port' => $config['ssh_port'] ?? 'not set',
                'ssh_user' => $config['ssh_user'] ?? 'not set',
                'ssh_key_exists' => ! empty($config['ssh_private_key_file']) && file_exists($config['ssh_private_key_file']),
                'ssh_key_file' => $config['ssh_private_key_file'] ?? 'not set',
                'endpoint' => $config['endpoint'] ?? 'not set',
            ]);

            throw new PolydockEngineServiceProviderInitializationException(
                'Failed to get Lagoon token - SSH may be failing or token command not working. Check logs for debug info.'
            );
        }

        $this->LagoonClient->initGraphqlClient();

        if ($debug) {
            $whoAmIData = $this->LagoonClient->whoAmI();
            $this->debug('Logged into lagoon: '.json_encode($whoAmIData));
        }
    }

    /**
     * Return the lagoon client.
     */
    public function getLagoonClient(): Client
    {
        return $this->LagoonClient;
    }

    /**
     * Return the max token age in minutes.
     */
    public function getMaxTokenAgeMinutes(): int
    {
        return self::MAX_TOKEN_AGE_MINUTES;
    }

    /**
     * Fixed name for this provider.
     */
    public function getName(): string
    {
        return 'FT-Lagoon-Client-Provider';
    }

    /**
     * Fixed description of this provider.
     */
    public function getDescription(): string
    {
        return 'An implementation of the FT Lagoon Client from ft-lagoon-php';
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
