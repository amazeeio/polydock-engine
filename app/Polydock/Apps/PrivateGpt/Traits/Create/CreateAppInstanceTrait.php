<?php

namespace App\Polydock\Apps\PrivateGpt\Traits\Create;

use App\Polydock\Apps\PrivateGpt\Interfaces\AmazeeAiOperationsInterface;
use App\Polydock\Apps\PrivateGpt\Interfaces\LagoonOperationsInterface;
use App\Polydock\Apps\PrivateGpt\Interfaces\LoggerInterface;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;

trait CreateAppInstanceTrait
{
    protected ?LoggerInterface $createLogger = null;

    protected ?LagoonOperationsInterface $createLagoonOps = null;

    protected ?AmazeeAiOperationsInterface $createAmazeeAiOps = null;

    /**
     * Setup trait dependencies
     */
    public function setupCreateTrait(
        ?LoggerInterface $logger = null,
        ?LagoonOperationsInterface $lagoonOps = null,
        ?AmazeeAiOperationsInterface $amazeeAiOps = null
    ): void {
        $this->createLogger = $logger;
        $this->createLagoonOps = $lagoonOps;
        $this->createAmazeeAiOps = $amazeeAiOps;
    }

    /**
     * Ensure trait is initialized with dependencies
     */
    private function ensureCreateTraitInitialized(): void
    {
        if ($this->createLogger === null && $this instanceof LoggerInterface) {
            $this->createLogger = $this;
        }
        if ($this->createLagoonOps === null && $this instanceof LagoonOperationsInterface) {
            $this->createLagoonOps = $this;
        }
        if ($this->createAmazeeAiOps === null && $this instanceof AmazeeAiOperationsInterface) {
            $this->createAmazeeAiOps = $this;
        }
    }

    public function createAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->ensureCreateTraitInitialized();

        $functionName = __FUNCTION__;
        $logContext = $this->createLogger?->getLogContext($functionName) ?? [];
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = false;

        $this->createLogger?->info($functionName.': starting', $logContext);

        $this->createLagoonOps?->validateAndSetupLagoon(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_CREATE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');

        $this->createLogger?->info($functionName.': starting for project: '.$projectName, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::CREATE_RUNNING
        )->save();

        $addOrgOwnerToProject = true;
        $createdProjectData = [];
        if ($this->lagoonClient) {
            $createdProjectData = $this->lagoonClient->createLagoonProjectInOrganization(
                $projectName,
                $appInstance->getKeyValue('lagoon-deploy-git'),
                $appInstance->getKeyValue('lagoon-deploy-branch'),
                $appInstance->getKeyValue('lagoon-deploy-branch'),
                (int) $appInstance->getKeyValue('lagoon-deploy-region-id'),
                $appInstance->getKeyValue('lagoon-deploy-private-key'),
                (int) $appInstance->getKeyValue('lagoon-deploy-organization-id'),
                $addOrgOwnerToProject
            );

            if (isset($createdProjectData['error'])) {
                $this->createLogger?->error($createdProjectData['error'][0]['message'], $logContext);
                $appInstance->setStatus(PolydockAppInstanceStatus::CREATE_FAILED, 'Failed to create Lagoon project')->save();

                return $appInstance;
            }

            if (! isset($createdProjectData['addProject']['id'])) {
                $appInstance->setStatus(PolydockAppInstanceStatus::CREATE_FAILED, 'Failed to create Lagoon project')->save();

                return $appInstance;
            }

            $appInstance->storeKeyValue('lagoon-project-id', $createdProjectData['addProject']['id']);

            $this->createAmazeeAiOps?->setAmazeeAiClientFromAppInstance($appInstance);

            $teamId = $appInstance->getKeyValue('amazee-ai-team-id');
            if ($teamId) {
                $credentials = $this->createAmazeeAiOps?->generateKeysForTeam($appInstance, $teamId) ?? [];

                $appInstance->storeKeyValue('amazee-ai-team-credentials', json_encode($credentials) ?: '');
            }
        }

        $this->createLogger?->info($functionName.': completed', $logContext + ['projectId' => $createdProjectData['addProject']['id'] ?? '']);
        $appInstance->setStatus(PolydockAppInstanceStatus::CREATE_COMPLETED)->save();

        return $appInstance;
    }
}
