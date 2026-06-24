<?php

namespace App\Polydock\Apps\Generic\Traits\Deploy;

use App\Polydock\Clients\Lagoon\LagoonClientInitializeRequiredToInteractException;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

trait DeployAppInstanceTrait
{
    /**
     * Handles deployment tasks for an app instance.
     *
     * This method is to deploy the app instance. It validates the instance
     * is in the correct status, sets it to running, executes deployment logic,
     * and marks it as completed.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to process
     * @return PolydockAppInstanceInterface The processed app instance
     *
     * @throws PolydockAppInstanceStatusFlowException If instance is not in PENDING_DEPLOY status
     * @throws LagoonClientInitializeRequiredToInteractException If the lagoon client required to interact
     */
    public function deployAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;
        $logContext = $this->getLogContext($functionName);
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = true;

        $this->info($functionName.': starting', $logContext);

        // Throws PolydockAppInstanceStatusFlowException
        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_DEPLOY,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $deployEnvironment = $appInstance->getKeyValue('lagoon-deploy-branch');
        $logContext['projectName'] = $projectName;
        $logContext['deployEnvironment'] = $deployEnvironment;

        $this->info($functionName.': starting for project: '.$projectName.' and environment: '.$deployEnvironment, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::DEPLOY_RUNNING,
            PolydockAppInstanceStatus::DEPLOY_RUNNING->getStatusMessage()
        )->save();

        $createdDeployment = $this->lagoonClient->deployProjectEnvironmentByName(
            $projectName,
            $deployEnvironment
        );

        if (isset($createdDeployment['error'])) {
            // Handle both array errors (from GraphQL) and string errors (from not found)
            $errorMessage = is_array($createdDeployment['error'])
                ? ($createdDeployment['error'][0]['message'] ?? json_encode($createdDeployment['error']))
                : $createdDeployment['error'];
            $this->error($errorMessage, $logContext);
            $appInstance->setStatus(PolydockAppInstanceStatus::DEPLOY_FAILED, 'Failed to create Lagoon project', $logContext + ['error' => $createdDeployment['error']])->save();

            return $appInstance;
        }

        $latestDeploymentName = $createdDeployment['deployEnvironmentBranch'] ?? null;

        if (empty($latestDeploymentName)) {
            $appInstance->setStatus(PolydockAppInstanceStatus::DEPLOY_FAILED, 'Failed to create Lagoon project', $logContext + ['error' => 'Missing deployment name'])->save();

            return $appInstance;
        }

        $appInstance->storeKeyValue('lagoon-latest-deployment-name', $latestDeploymentName);

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::DEPLOY_RUNNING, 'Deploy running')->save();

        return $appInstance;
    }
}
