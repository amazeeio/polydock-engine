<?php

namespace App\Services;

use App\Polydock\Clients\Lagoon\Client;
use App\Polydock\Clients\Lagoon\Ssh;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class LagoonClientService
{
    /**
     * Build and configure a Client using the project's standard lagoon configuration
     *
     * @throws \Exception
     */
    public function getAuthenticatedClient(array $overrides = []): Client
    {
        $allowedOverrideKeys = ['timeout', 'connect_timeout'];
        $filteredOverrides = array_intersect_key($overrides, array_flip($allowedOverrideKeys));

        $clientConfig = array_merge($this->getClientConfig(), $filteredOverrides);

        if (! $clientConfig['ssh_private_key_file'] || ! file_exists($clientConfig['ssh_private_key_file'])) {
            $msg = 'Global SSH private key not found at: '.($clientConfig['ssh_private_key_file'] ?: 'not set');
            Log::error($msg);
            throw new \Exception($msg);
        }

        $token = $this->getLagoonToken($clientConfig);
        if (empty($token)) {
            $msg = 'Failed to retrieve Lagoon API token. Ensure the SSH key at '.$clientConfig['ssh_private_key_file'].' is valid and authorized in Lagoon.';
            Log::error($msg);
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
            $home = getenv('HOME');
            if ($home === false || $home === '') {
                $home = $_SERVER['HOME'] ?? null;
            }

            if (! empty($home)) {
                $keyFile = rtrim($home, '/').'/.ssh/id_rsa';
            } else {
                // Leave $keyFile empty; it will be validated later in getAuthenticatedClient()
                $keyFile = null;
            }
        }

        // Fallback or override via content if provided (from config, not env())
        $keyContent = config('polydock.ftlagoon_private_key_content');

        if ($keyContent) {
            // Use storage/app/ssh as a safe default for writing the temp key
            $baseDir = storage_path('app/ssh');

            $tempKeyFile = $baseDir.'/env_id_rsa';

            $dir = dirname($tempKeyFile);
            if (! is_dir($dir)) {
                if (! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
                    throw new \RuntimeException('Failed to create SSH key directory: '.$dir);
                }
            }

            if (! file_exists($tempKeyFile) || file_get_contents($tempKeyFile) !== $keyContent) {
                $tmpFile = $tempKeyFile.'.tmp';

                $bytesWritten = @file_put_contents($tmpFile, $keyContent, LOCK_EX);
                if ($bytesWritten === false) {
                    @unlink($tmpFile);
                    throw new \RuntimeException('Failed to write SSH private key to temporary file: '.$tmpFile);
                }

                if (! @chmod($tmpFile, 0600)) {
                    @unlink($tmpFile);
                    throw new \RuntimeException('Failed to set permissions on SSH private key file: '.$tmpFile);
                }

                if (! @rename($tmpFile, $tempKeyFile)) {
                    @unlink($tmpFile);
                    throw new \RuntimeException('Failed to move SSH private key file into place: '.$tempKeyFile);
                }
            }
            $keyFile = $tempKeyFile;
        }

        return [
            'ssh_user' => $sshConfig['ssh_user'] ?? 'lagoon',
            'ssh_server' => $sshConfig['ssh_server'] ?? 'ssh.lagoon.amazeeio.cloud',
            'ssh_port' => $sshConfig['ssh_port'] ?? '32222',
            'endpoint' => $sshConfig['endpoint'] ?? 'https://api.lagoon.amazeeio.cloud/graphql',
            'ssh_private_key_file' => $keyFile,
            'connect_timeout' => $sshConfig['connect_timeout'] ?? 5.0,
            'timeout' => $sshConfig['timeout'] ?? 60.0,
        ];
    }

    /**
     * Helper to get a token either from a bound fetcher or directly via SSH.
     */
    public function getLagoonToken(?array $config = null): string
    {
        $config ??= $this->getClientConfig();

        if (app()->bound('polydock.lagoon.token_fetcher')) {
            return app('polydock.lagoon.token_fetcher')($config);
        }

        $ssh = Ssh::createLagoonConfigured(
            user: $config['ssh_user'],
            server: $config['ssh_server'],
            port: Ssh::normalizePort($config['ssh_port'] ?? 32222),
            privateKeyFile: $config['ssh_private_key_file']
        );

        // Add IdentitiesOnly to prevent fallback to local keys, making it fail faster and more predictably
        $ssh->addExtraOption('-o IdentitiesOnly=yes');

        $sshCommand = $ssh->getTokenCommand();
        $process = Process::fromShellCommandline($sshCommand);
        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful()) {
            return ltrim(rtrim($process->getOutput()));
        }

        Log::error('Lagoon SSH token fetch failed', [
            'exit_code' => $process->getExitCode(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
            'command' => $sshCommand,
            'key_file' => $config['ssh_private_key_file'],
        ]);

        return '';
    }
}
