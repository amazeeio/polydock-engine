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
            $msg = 'Global SSH private key not found at: '.($clientConfig['ssh_private_key_file'] ?: 'not set');
            \Log::error($msg);
            throw new \Exception($msg);
        }

        $token = $this->getLagoonToken($clientConfig);
        if (empty($token)) {
            $msg = 'Failed to retrieve Lagoon API token. Ensure the SSH key at '.$clientConfig['ssh_private_key_file'].' is valid and authorized in Lagoon.';
            \Log::error($msg);
            throw new \Exception($msg);
        }

        return $this->buildClientWithToken($clientConfig, $token);
    }

    /**
     * Build a Client using a pre-fetched token (useful when the token is cached externally)
     *
     * @throws \Exception
     */
    public function buildClientWithToken(array $clientConfig, string $token): Client
    {
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

        // Primary source: config (which reads FTLAGOON_PRIVATE_KEY_FILE)
        $keyFile = $sshConfig['ssh_private_key_file'] ?? null;

        // Fallback to POLYDOCK_LAGOON_DEPLOY_PRIVATE_KEY_FILE if first is missing or default
        if (empty($keyFile) || $keyFile === 'tests/fixtures/lagoon-private-key') {
            $keyFile = config('polydock.lagoon_deploy_private_key_file');
        }

        // Final fallback to system default
        if (empty($keyFile)) {
            $keyFile = getenv('HOME').'/.ssh/id_rsa';
        }

        // Fallback or override via content if provided
        $keyContent = env('FTLAGOON_PRIVATE_KEY_CONTENT');

        if ($keyContent) {
            // Use storage/app/ssh as a safe default for writing the temp key
            $baseDir = storage_path('app/ssh');

            $tempKeyFile = $baseDir.'/env_id_rsa';

            if (! is_dir(dirname($tempKeyFile))) {
                mkdir(dirname($tempKeyFile), 0755, true);
            }

            if (! file_exists($tempKeyFile) || file_get_contents($tempKeyFile) !== $keyContent) {
                file_put_contents($tempKeyFile, $keyContent);
                chmod($tempKeyFile, 0600);
            }
            $keyFile = $tempKeyFile;
        }

        return [
            'ssh_user' => $sshConfig['ssh_user'] ?? 'lagoon',
            'ssh_server' => $sshConfig['ssh_server'] ?? 'ssh.lagoon.amazeeio.cloud',
            'ssh_port' => $sshConfig['ssh_port'] ?? '32222',
            'endpoint' => $sshConfig['endpoint'] ?? 'https://api.lagoon.amazeeio.cloud/graphql',
            'ssh_private_key_file' => $keyFile,
        ];
    }

    /**
     * Helper to get a token either from a bound fetcher or directly via SSH.
     */
    public function getLagoonToken(?array $config = null): string
    {
        $config = $config ?? $this->getClientConfig();

        if (app()->bound('polydock.lagoon.token_fetcher')) {
            return app('polydock.lagoon.token_fetcher')($config);
        }

        $ssh = Ssh::createLagoonConfigured(
            user: $config['ssh_user'],
            server: $config['ssh_server'],
            port: $config['ssh_port'],
            privateKeyFile: $config['ssh_private_key_file']
        );

        // Add IdentitiesOnly to prevent fallback to local keys, making it fail faster and more predictably
        $ssh->addExtraOption('-o IdentitiesOnly=yes');

        $sshCommand = $ssh->getTokenCommand();
        $result = $ssh->executeRawSshCommand($sshCommand);

        if ($result['successful']) {
            return ltrim(rtrim($result['output']));
        }

        \Log::error('Lagoon SSH token fetch failed', [
            'exit_code' => $result['result'],
            'output' => $result['output'],
            'error' => $result['error'],
            'command' => $sshCommand,
            'key_file' => $config['ssh_private_key_file'],
        ]);

        return '';
    }
}
