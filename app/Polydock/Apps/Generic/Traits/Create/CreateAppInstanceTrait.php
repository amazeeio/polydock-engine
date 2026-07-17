<?php

namespace App\Polydock\Apps\Generic\Traits\Create;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

trait CreateAppInstanceTrait
{
    /**
     * Handles creation tasks for an app instance.
     *
     * This method is to create the Lagoon project for the app instance. It validates the instance
     * is in the correct status, sets it to running, executes creation logic,
     * and marks it as completed.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to process
     * @return PolydockAppInstanceInterface The processed app instance
     *
     * @throws PolydockAppInstanceStatusFlowException If instance is not in PENDING_CREATE status
     */
    public function createAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;

        return $this->runLifecyclePhase(
            $appInstance,
            $functionName,
            PolydockAppInstanceStatus::PENDING_CREATE,
            PolydockAppInstanceStatus::CREATE_RUNNING,
            PolydockAppInstanceStatus::CREATE_COMPLETED,
            PolydockAppInstanceStatus::CREATE_FAILED,
            function (PolydockAppInstanceInterface $appInstance, array $logContext) use ($functionName): ?PolydockAppInstanceInterface {
                $projectName = $appInstance->getKeyValue('lagoon-project-name');

                $this->info("{$functionName}: starting for project: {$projectName}", $logContext);

                $addOrgOwnerToProject = true;
                $autoIdle = (int) $appInstance->getKeyValue('lagoon-auto-idle') ?? 0;
                $productionEnvironment = $appInstance->getKeyValue('lagoon-production-environment')
                    ?: $appInstance->getKeyValue('lagoon-deploy-branch');

                $createdProjectData = $this->lagoonClient->createLagoonProjectInOrganization(
                    projectName: $projectName,
                    gitUrl: $appInstance->getKeyValue('lagoon-deploy-git'),
                    branches: $appInstance->getKeyValue('lagoon-deploy-branch'),
                    productionEnvironment: $productionEnvironment,
                    clusterId: $appInstance->getKeyValue('lagoon-deploy-region-id'),
                    privateKey: $appInstance->getKeyValue('lagoon-deploy-private-key'),
                    orgId: $appInstance->getKeyValue('lagoon-deploy-organization-id'),
                    addOrgOwnerToProject: $addOrgOwnerToProject,
                    autoIdle: $autoIdle
                );

                if (isset($createdProjectData['error'])) {
                    // Handle both array errors (from GraphQL) and string errors (from not found)
                    $errorMessage = \is_array($createdProjectData['error'])
                        ? ($createdProjectData['error'][0]['message'] ?? \json_encode($createdProjectData['error']))
                        : $createdProjectData['error'];
                    $this->error($errorMessage, $logContext + ['error' => $createdProjectData['error']]);
                    $appInstance->setStatus(PolydockAppInstanceStatus::CREATE_FAILED, 'Failed to create Lagoon project')->save();

                    return $appInstance;
                }

                if (! isset($createdProjectData['addProject']['id'])) {
                    $this->error('Failed to create Lagoon project: Missing project id', $logContext);
                    $appInstance->setStatus(PolydockAppInstanceStatus::CREATE_FAILED, 'Failed to create Lagoon project')->save();

                    return $appInstance;
                }

                $appInstance->storeKeyValue('lagoon-project-id', $createdProjectData['addProject']['id']);

                return null;
            },
            'Create completed',
            validateLagoonProjectId: false,
        );
    }
}
