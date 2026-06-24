<?php

namespace App\Polydock\Clients\Lagoon;

use Spatie\Ssh\Ssh as SpatieSsh;
use Symfony\Component\Process\Process;

/**
 * Class Ssh
 *
 * Extends Spatie's SSH implementation to provide Lagoon-specific SSH functionality.
 * This class handles SSH connections and commands specifically for interacting with Lagoon services.
 */
class Ssh extends SpatieSsh
{
    /**
     * Executes a command over SSH to retrieve a Lagoon API token
     *
     * This method connects to the Lagoon server via SSH and executes the 'token' command
     * to obtain an authentication token for API access.
     *
     * @return string The cleaned Lagoon API token with leading/trailing whitespace removed
     */
    public function executeLagoonGetToken(): string
    {
        $sshCommand = $this->getTokenCommand();

        $process = $this->run($sshCommand);

        $token = $process->getOutput();

        return ltrim(rtrim($token));
    }

    public function getTokenCommand(): string
    {
        $extraOptions = implode(' ', $this->getExtraOptions());
        $target = $this->getTargetForSsh();

        return "ssh {$extraOptions} {$target} token";
    }

    public function getCommandForExecute(string $execute, string $serviceName = 'cli', string $containerName = 'cli'): string
    {
        $extraOptions = implode(' ', $this->getExtraOptions());
        $target = $this->getTargetForSsh();

        return "ssh {$extraOptions} {$target} service={$serviceName} container={$containerName} $execute";
    }

    public function executeSShCommand(string $command, string $serviceName = 'cli', string $containerName = 'cli', ?string $input = null): array
    {
        $execute = $this->getCommandForExecute($command, $serviceName, $containerName);

        if ($input !== null) {
            $this->configureProcess(function (Process $process) use ($input) {
                $process->setInput($input);
            });
        }

        $process = $this->run($execute);

        $output = $process->getOutput();
        $error = $process->getErrorOutput();

        return [
            'command' => $execute,
            'result' => $process->getExitCode(),
            'result_text' => $process->getExitCodeText(),
            'output' => $output,
            'error' => $error,
        ];
    }

    /**
     * Creates a pre-configured SSH connection for Lagoon
     *
     * This static factory method creates an SSH connection with all the required
     * configuration options for connecting to Lagoon services. It sets up:
     * - Custom port
     * - Private key authentication
     * - Disabled strict host key checking for automation
     * - Removed bash shell
     * - Quiet mode for cleaner output
     *
     * @param  string  $user  The SSH username to connect with
     * @param  string  $server  The SSH server hostname
     * @param  string  $port  The SSH port number to use
     * @param  string  $privateKeyFile  Path to the private key file for authentication
     * @return static Returns configured SSH connection instance
     *
     * @throws \Exception
     */
    public static function createLagoonConfigured(string $user, string $server, string $port, string $privateKeyFile): static
    {
        return static::create($user, $server)
            ->usePort($port)
            ->usePrivateKey($privateKeyFile)
            ->disableStrictHostKeyChecking()
            ->removeBash()
            ->enableQuietMode()
            ->addExtraOption('-o IdentityAgent=none');
    }
}
