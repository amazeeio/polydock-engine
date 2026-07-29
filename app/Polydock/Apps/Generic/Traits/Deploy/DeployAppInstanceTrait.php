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

        // Note: deploy hands off to the poll trait, so its "completed" state
        // is DEPLOY_RUNNING (not a *_COMPLETED status).
        return $this->runLifecyclePhase(
            $appInstance,
            $functionName,
            PolydockAppInstanceStatus::PENDING_DEPLOY,
            PolydockAppInstanceStatus::DEPLOY_RUNNING,
            PolydockAppInstanceStatus::DEPLOY_RUNNING,
            PolydockAppInstanceStatus::DEPLOY_FAILED,
            function (PolydockAppInstanceInterface $appInstance, array $logContext) use ($functionName): ?PolydockAppInstanceInterface {
                $projectName = $appInstance->getKeyValue('lagoon-project-name');
                $deployEnvironment = $appInstance->getKeyValue('lagoon-deploy-branch');
                $logContext['projectName'] = $projectName;
                $logContext['deployEnvironment'] = $deployEnvironment;

                $this->info($functionName.': starting for project: '.$projectName.' and environment: '.$deployEnvironment, $logContext);

                $createdDeployment = $this->lagoonClient->deployProjectEnvironmentByName(
                    $projectName,
                    $deployEnvironment
                );

                if (isset($createdDeployment['error'])) {
                    // Handle both array errors (from GraphQL) and string errors (from not found)
                    $errorMessage = is_array($createdDeployment['error'])
                        ? ($createdDeployment['error'][0]['message'] ?? json_encode($createdDeployment['error']))
                        : $createdDeployment['error'];
                    $this->error($errorMessage, $logContext + ['error' => $createdDeployment['error']]);
                    $appInstance->setStatus(PolydockAppInstanceStatus::DEPLOY_FAILED, 'Failed to create Lagoon project')->save();

                    return $appInstance;
                }

                $latestDeploymentName = $createdDeployment['deployEnvironmentBranch'] ?? null;

                if (empty($latestDeploymentName)) {
                    $this->error('Failed to create Lagoon project: Missing deployment name', $logContext);
                    $appInstance->setStatus(PolydockAppInstanceStatus::DEPLOY_FAILED, 'Failed to create Lagoon project')->save();

                    return $appInstance;
                }

                $appInstance->storeKeyValue('lagoon-latest-deployment-name', $latestDeploymentName);

                return null;
            },
            'Deploy running',
        );
    }
}
