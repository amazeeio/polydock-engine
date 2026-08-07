<?php

namespace App\Services;

use App\Polydock\Clients\Lagoon\Client;
use App\Polydock\Clients\Lagoon\Ssh;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class LagoonClientService
{
    /**
     * Build and configure a Client using the project's standard lagoon configuration
     *
     * @param  array<string, mixed>  $overrides
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
        if ($token === '' || $token === '0') {
            $msg = 'Failed to retrieve Lagoon API token. Ensure the SSH key at '.$clientConfig['ssh_private_key_file'].' is valid and authorized in Lagoon.';
            Log::error($msg);
            throw new \Exception($msg);
        }

        return $this->buildClientWithToken($clientConfig, $token);
    }

    /**
     * Build a Client using a pre-fetched token (useful when the token is cached externally)
     *
     * @param  array<string, mixed>  $clientConfig
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
     *
     * @return array<string, mixed>
     */
    public function getClientConfig(): array
    {
        $sshConfig = config('polydock.service_providers_singletons.PolydockServiceProviderFTLagoon', []);

        // Primary source: config (which reads FTLAGOON_PRIVATE_KEY_FILE)
        $keyFile = $sshConfig['ssh_private_key_file'] ?? null;

        // Fallback to system default
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
     * Cache key for a config's token — public static so tests and the
     * implementation can never drift on the derivation.
     *
     * @param  array<string, mixed>  $config
     */
    public static function tokenCacheKey(array $config): string
    {
        return 'lagoon-client-service-token:'.sha1(implode('|', [
            $config['ssh_user'] ?? '',
            $config['ssh_server'] ?? '',
            (string) ($config['ssh_port'] ?? ''),
            $config['ssh_private_key_file'] ?? '',
            // Endpoint included so configs sharing SSH credentials but
            // targeting different Lagoon cores never share a token.
            $config['endpoint'] ?? '',
        ]));
    }

    /**
     * Helper to get a token either from a bound fetcher or directly via SSH.
     *
     * Successful tokens are cached briefly (mirroring the FTLagoon provider
     * stack's 2-minute max token age) so redeploy/poll bursts don't pay an
     * SSH round-trip per job. Failures ('' return) are never cached — one
     * SSH blip must not poison every caller for the TTL.
     *
     * @param  array<string, mixed>  $config
     */
    public function getLagoonToken(?array $config = null): string
    {
        $config ??= $this->getClientConfig();

        if (app()->bound('polydock.lagoon.token_fetcher')) {
            return app('polydock.lagoon.token_fetcher')($config);
        }

        $cacheKey = self::tokenCacheKey($config);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->fetchLagoonTokenOverSsh($config);

        if ($token !== '') {
            Cache::put($cacheKey, $token, now()->addSeconds(110));
        }

        return $token;
    }

    /**
     * Mint a fresh token over SSH.
     *
     * @param  array<string, mixed>  $config
     */
    private function fetchLagoonTokenOverSsh(array $config): string
    {
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
