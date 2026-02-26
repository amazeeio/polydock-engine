<?php

namespace App\Services;

use FreedomtechHosting\FtLagoonPhp\Client;
use FreedomtechHosting\FtLagoonPhp\Ssh;

class LagoonClientService
{
    /**
     * Build and configure a Client using the project's standard lagoon configuration
     *
     * @throws \Exception
     */
    public function getAuthenticatedClient(): Client
    {
        $clientConfig = $this->getClientConfig();

        if (! $clientConfig['ssh_private_key_file'] || ! file_exists($clientConfig['ssh_private_key_file'])) {
            throw new \Exception('Global SSH private key not found.');
        }

        $token = $this->getLagoonToken($clientConfig);
        if (empty($token)) {
            throw new \Exception('Failed to retrieve Lagoon API token.');
        }

        if (app()->bound(Client::class)) {
            $client = app(Client::class);
        } else {
            $client = app()->makeWith(Client::class, ['config' => $clientConfig]);
        }

        $client->setLagoonToken($token);
        $client->initGraphqlClient();

        return $client;
    }

    /**
     * Get the standard client configuration array
     */
    public function getClientConfig(): array
    {
        $sshConfig = config('polydock.service_providers_singletons.PolydockServiceProviderFTLagoon', []);

        return [
            'ssh_user' => $sshConfig['ssh_user'] ?? 'lagoon',
            'ssh_server' => $sshConfig['ssh_server'] ?? 'ssh.lagoon.amazeeio.cloud',
            'ssh_port' => $sshConfig['ssh_port'] ?? '32222',
            'endpoint' => $sshConfig['endpoint'] ?? 'https://api.lagoon.amazeeio.cloud/graphql',
            'ssh_private_key_file' => $sshConfig['ssh_private_key_file'] ?? getenv('HOME').'/.ssh/id_rsa',
        ];
    }

    /**
     * Helper to get a token either from a bound fetcher or directly via SSH.
     */
    protected function getLagoonToken(array $config): string
    {
        if (app()->bound('polydock.lagoon.token_fetcher')) {
            return app('polydock.lagoon.token_fetcher')($config);
        }

        $ssh = Ssh::createLagoonConfigured(
            user: $config['ssh_user'],
            server: $config['ssh_server'],
            port: $config['ssh_port'],
            privateKeyFile: $config['ssh_private_key_file']
        );

        return $ssh->executeLagoonGetToken();
    }
}
