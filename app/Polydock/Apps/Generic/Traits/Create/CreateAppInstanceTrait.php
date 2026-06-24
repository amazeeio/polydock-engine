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
        $logContext = $this->getLogContext($functionName);
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = false;

        $this->info("{$functionName}: starting", $logContext);

        // Throws PolydockAppInstanceStatusFlowException
        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_CREATE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');

        $this->info("{$functionName}: starting for project: {$projectName}", $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::CREATE_RUNNING,
            PolydockAppInstanceStatus::CREATE_RUNNING->getStatusMessage()
        )->save();

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
                ? ($createdProjectData['error'][0]['message'] ?? json_encode($createdProjectData['error']))
                : $createdProjectData['error'];
            $this->error($errorMessage, $logContext);
            $appInstance->setStatus(PolydockAppInstanceStatus::CREATE_FAILED, 'Failed to create Lagoon project', $logContext + ['error' => $createdProjectData['error']])->save();

            return $appInstance;
        }

        if (! isset($createdProjectData['addProject']['id'])) {
            $appInstance->setStatus(PolydockAppInstanceStatus::CREATE_FAILED, 'Failed to create Lagoon project', $logContext + ['error' => 'Missing project id'])->save();

            return $appInstance;
        }

        $appInstance->storeKeyValue('lagoon-project-id', $createdProjectData['addProject']['id']);

        $this->info("{$functionName}: completed", $logContext + ['projectId' => $createdProjectData['addProject']['id']]);
        $appInstance->setStatus(PolydockAppInstanceStatus::CREATE_COMPLETED, 'Create completed')->save();

        return $appInstance;
    }
}
