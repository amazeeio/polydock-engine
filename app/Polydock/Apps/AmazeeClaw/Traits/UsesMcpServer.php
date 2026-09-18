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
     * Resolves the knob through the usual instance -> app -> store app chain.
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
     * Records an MCP decision that arrived with the registration request (MOAD
     * and other callers post it as request data), so the normal
     * instance -> app -> store app resolution picks it up from here on.
     *
     * @param  array<string, mixed>  $requestData
     */
    protected function captureMcpServerRequestData(PolydockAppInstanceInterface $appInstance, array $requestData): void
    {
        if (! array_key_exists('mcp_enabled', $requestData)) {
            return;
        }

        $raw = $requestData['mcp_enabled'];
        $normalized = match (true) {
            is_bool($raw) => $raw ? 'on' : 'off',
            is_string($raw) || is_int($raw) => in_array(strtolower(trim((string) $raw)), ['on', 'true', '1', 'yes', 'enabled'], true) ? 'on' : 'off',
            default => 'off',
        };

        $appInstance->storeKeyValue('instance_config_mcp_enabled', $normalized);
    }

    /**
     * Brings the Lagoon variable in line with the knob, minting the consumer
     * token once and reusing it afterwards.
     *
     * Idempotent, and called from both post-create and claim: pre-warmed
     * instances run post-create long before anyone asks for MCP, so claim is
     * the first point where a per-instance (or MOAD-supplied) answer exists.
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
            $this->deleteMcpServerTokenVariable($appInstance, $logContext);
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
     * Deleting the variable is how the endpoint goes away, but a missing
     * variable is already the desired state — log and carry on rather than
     * failing the lifecycle phase over it.
     *
     * @param  array<string, mixed>  $logContext
     */
    protected function deleteMcpServerTokenVariable(PolydockAppInstanceInterface $appInstance, array $logContext = []): void
    {
        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        if (! is_string($projectName) || $projectName === '') {
            return;
        }

        $result = $this->lagoonClient->deleteProjectVariableByName($projectName, self::MCP_TOKEN_VARIABLE);
        if (isset($result['error'])) {
            $this->warning('Could not delete the MCP token variable', $logContext + [
                'projectName' => $projectName,
                'variable' => self::MCP_TOKEN_VARIABLE,
                'error' => $result['error'],
            ]);
        }
    }
}
