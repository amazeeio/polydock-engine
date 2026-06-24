<?php

namespace App\Polydock\Apps\PrivateGpt\Traits;

use App\Polydock\Apps\PrivateGpt\Client\AmazeeAiClient;
use App\Polydock\Apps\PrivateGpt\Generated\Dto\APIToken;
use App\Polydock\Apps\PrivateGpt\Generated\Dto\LlmKeysResponse;
use App\Polydock\Apps\PrivateGpt\Generated\Dto\TeamResponse;
use App\Polydock\Apps\PrivateGpt\Interfaces\LoggerInterface;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

/**
 * Trait UsesAmazeeAiDevmode
 */
trait UsesAmazeeAiDevmode
{
    use UsesAmazeeAi {
        UsesAmazeeAi::setupAmazeeAiTrait as private originalSetupAmazeeAiTrait;
        UsesAmazeeAi::ensureAmazeeAiTraitInitialized as private originalEnsureAmazeeAiTraitInitialized;
        UsesAmazeeAi::getAmazeeAiClient as private originalGetAmazeeAiClient;
        UsesAmazeeAi::setAmazeeAiClientFromAppInstance as private originalSetAmazeeAiClientFromAppInstance;
        UsesAmazeeAi::pingAmazeeAi as private originalPingAmazeeAi;
        UsesAmazeeAi::createTeamAndSetupAdministrator as private originalCreateTeamAndSetupAdministrator;
        UsesAmazeeAi::generateKeysForTeam as private originalGenerateKeysForTeam;
        UsesAmazeeAi::getTeamDetails as private originalGetTeamDetails;
    }

    protected ?bool $devModeOverride = false;

    /**
     * Set the whole Client into Dev mode
     */
    public function setAmazeeAiClientDevMode(): void
    {
        $this->devModeOverride = true;
    }

    /**
     * Setup the trait dependencies
     */
    public function setupAmazeeAiTrait(?LoggerInterface $logger = null): void
    {
        if ($this->devModeOverride) {
            $this->setAmazeeAiClientDevMode();
        } else {
            $this->originalSetupAmazeeAiTrait($logger);
        }
    }

    /**
     * Ensure trait is initialized with dependencies
     */
    private function ensureAmazeeAiTraitInitialized(): void
    {
        if ($this->devModeOverride) {
            return;
        }
        $this->originalEnsureAmazeeAiTraitInitialized();
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    protected function getAmazeeAiClient(): AmazeeAiClient
    {
        return $this->originalGetAmazeeAiClient();
    }

    public function setAmazeeAiClientFromAppInstance(PolydockAppInstanceInterface $appInstance): void
    {
        $this->originalSetAmazeeAiClientFromAppInstance($appInstance);
    }

    public function pingAmazeeAi(): bool
    {
        if ($this->devModeOverride) {
            return true;
        }

        return $this->originalPingAmazeeAi();
    }

    public function createTeamAndSetupAdministrator(PolydockAppInstanceInterface $appInstance): TeamResponse
    {
        if ($this->devModeOverride) {
            return new TeamResponse('devmode-name', 'devmode-email@example.com', 1, true, true, '');
        }

        return $this->originalCreateTeamAndSetupAdministrator($appInstance);
    }

    /**
     * @return array{team_id: string, backend_key: APIToken, llm_key: LlmKeysResponse}
     *
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function generateKeysForTeam(PolydockAppInstanceInterface $appInstance, string $teamId): array
    {
        if ($this->devModeOverride) {

            $llmRegionId = $appInstance->getKeyValue('amazee-ai-backend-region-id');
            if (empty($llmRegionId)) {
                throw new PolydockAppInstanceStatusFlowException('amazee.ai LLM region is required to generate LLM keys');
            }

            return [
                'team_id' => 'devmode-team-id',
                'backend_key' => new APIToken('devmode-token', 1, 'token', 'created-at', 1, 'last-used-at'),
                'llm_key' => new LlmKeysResponse(1, 'database-name-here', 'llmkey-name', 'database-host', 'database-username', 'database-password', 'litellm-token', 'litellm-api-url', 'region-name', 'created-at', 1, 1),
            ];
        }

        return $this->originalGenerateKeysForTeam($appInstance, $teamId);
    }

    public function getTeamDetails(string $teamId): TeamResponse
    {
        if ($this->devModeOverride) {
            return new TeamResponse('devmode-name', 'devmode-email@example.com', 1, true, true, '');
        }

        return $this->originalGetTeamDetails($teamId);
    }
}
