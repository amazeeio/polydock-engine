<?php

declare(strict_types=1);

namespace App\Polydock\Apps\AmazeeClaw\Traits;

use App\Polydock\Core\PolydockAppInstanceInterface;

/**
 * Per-instance MCP server knob.
 *
 * When on, the instance exposes an MCP endpoint at https://<instance>/mcp that
 * other people's MCP clients connect to. The base image gates the whole feature
 * on the OPENCLAW_MCP_TOKEN project variable this trait writes: no variable, no
 * plugin load path, no endpoint.
 *
 * Applied at post-create, which is the last point before the instance is first
 * deployed. A Lagoon variable only reaches the container on a deploy, so a
 * pre-warmed instance — already built and deployed before anyone is allocated
 * to it — follows its store app's setting; the per-instance override applies to
 * instances created on demand and to any later redeploy or upgrade.
 *
 * Depends on resolveInstanceOrAppConfig() from UsesManualAmazeeAiCredentials,
 * which the app class composes alongside this trait.
 */
trait UsesMcpServer
{
    /** Instance data key holding the generated consumer token (encrypted at rest). */
    public const string MCP_TOKEN_KEY = 'openclaw-mcp-token';

    /** Lagoon project variable the base image reads to switch the endpoint on. */
    public const string MCP_TOKEN_VARIABLE = 'OPENCLAW_MCP_TOKEN';

    /**
     * Resolves the knob through the usual instance -> app -> store app chain,
     * so it is set the same way as every other instance config field
     * (instance_config_mcp_enabled, stored from the registration request).
     *
     * The value is 'on'/'off' rather than a boolean because the resolver treats
     * an empty string as "not set and inherit", which would make an instance
     * unable to turn off what the store app turned on.
     */
    public function resolveMcpServerEnabled(PolydockAppInstanceInterface $appInstance): bool
    {
        return strtolower(trim($this->resolveInstanceOrAppConfig($appInstance, 'mcp_enabled'))) === 'on';
    }

    /**
     * Brings the Lagoon variable in line with the knob, minting the consumer
     * token once and reusing it afterwards.
     *
     * @param  array<string, mixed>  $logContext
     */
    protected function ensureMcpServerConfiguration(PolydockAppInstanceInterface $appInstance, array $logContext = []): void
    {
        $storedToken = $appInstance->getKeyValue(self::MCP_TOKEN_KEY);
        $existingToken = is_string($storedToken) ? $storedToken : '';

        if (! $this->resolveMcpServerEnabled($appInstance)) {
            if ($existingToken === '') {
                // Never enabled — don't spend a Lagoon API call per instance per
                // deploy telling it about a feature nobody asked for.
                return;
            }
            $this->info('MCP server turned off — removing the token variable', $logContext);
            if (! $this->deleteMcpServerTokenVariable($appInstance, $logContext)) {
                // Keep the stored token: it is the only record that a variable is
                // still out there granting access. Clearing it here would make
                // every later run take the "never enabled" branch above and leave
                // the endpoint live forever.
                return;
            }
            $appInstance->storeKeyValue(self::MCP_TOKEN_KEY, '');

            return;
        }

        $token = $existingToken !== '' ? $existingToken : bin2hex(random_bytes(32));
        if ($token !== $existingToken) {
            $appInstance->storeKeyValue(self::MCP_TOKEN_KEY, $token);
        }

        $this->addOrUpdateLagoonProjectVariable($appInstance, self::MCP_TOKEN_VARIABLE, $token, 'GLOBAL');
        $this->info('MCP server enabled', $logContext + [
            'variable' => self::MCP_TOKEN_VARIABLE,
            'tokenIssued' => $existingToken === '',
        ]);
    }

    /**
     * Deletes the token variable, reporting whether the endpoint is actually
     * revoked. Lagoon returns GraphQL errors in the payload rather than
     * throwing, so the caller has to be told about a failure to retry it.
     *
     * @param  array<string, mixed>  $logContext
     * @return bool True when the variable is gone (or the project never had one).
     */
    protected function deleteMcpServerTokenVariable(PolydockAppInstanceInterface $appInstance, array $logContext = []): bool
    {
        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        if (! is_string($projectName) || $projectName === '') {
            $this->warning('Cannot revoke the MCP token without a Lagoon project name', $logContext);

            return false;
        }

        $result = $this->lagoonClient->deleteProjectVariableByName($projectName, self::MCP_TOKEN_VARIABLE);
        if (isset($result['error'])) {
            $this->warning('Could not delete the MCP token variable — will retry on the next run', $logContext + [
                'projectName' => $projectName,
                'variable' => self::MCP_TOKEN_VARIABLE,
                'error' => $result['error'],
            ]);

            return false;
        }

        return true;
    }
}
