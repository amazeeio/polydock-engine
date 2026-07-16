<?php

namespace App\Polydock\Apps\Generic\Traits\Remove;

use App\Polydock\Clients\Lagoon\LagoonClientInitializeRequiredToInteractException;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

trait RemoveAppInstanceTrait
{
    /**
     * Handles removal tasks for an app instance.
     *
     * This method is to remove the app instance. It validates the instance
     * is in the correct status, sets it to running, executes removal logic,
     * and marks it as completed.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to process
     * @return PolydockAppInstanceInterface The processed app instance
     *
     * @throws PolydockAppInstanceStatusFlowException If instance is not in PENDING_REMOVE status
     * @throws LagoonClientInitializeRequiredToInteractException If the lagoon client requires interact
     */
    public function removeAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;
        $logContext = $this->getLogContext($functionName);

        $this->info($functionName.': starting', $logContext);

        // Adopted (claimed) projects are pre-existing environments Polydock did
        // not create. Removing one means detaching: drop the Polydock record but
        // leave the running Lagoon environment intact — never delete it, and
        // never touch Lagoon. This guard runs before the Lagoon ping/validation
        // below so a detach succeeds even when Lagoon is unreachable.
        if ($appInstance->getKeyValue('adopted')) {
            $this->validateAppInstanceStatusIsExpected($appInstance, PolydockAppInstanceStatus::PENDING_REMOVE);
            $this->info($functionName.': adopted project — detaching, leaving Lagoon environment intact', $logContext);
            $appInstance->setStatus(
                PolydockAppInstanceStatus::REMOVE_COMPLETED,
                'Adopted project detached (Lagoon environment left intact)'
            )->save();

            return $appInstance;
        }

        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = true;

        // Throws PolydockAppInstanceStatusFlowException
        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_REMOVE,
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

        $this->info($functionName.': starting for project: '.$projectName, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::REMOVE_RUNNING,
            PolydockAppInstanceStatus::REMOVE_RUNNING->getStatusMessage()
        )->save();

        $removedEnvironment = $this->lagoonClient->deleteProjectEnvironmentByName(
            $projectName,
            $deployEnvironment
        );

        if (isset($removedEnvironment['error'])) {
            // Handle both array errors (from GraphQL) and string errors (from not found)
            $errorMessage = is_array($removedEnvironment['error'])
                ? ($removedEnvironment['error'][0]['message'] ?? json_encode($removedEnvironment['error']))
                : $removedEnvironment['error'];
            $this->error($errorMessage, $logContext + ['error' => $removedEnvironment['error']]);
            $appInstance->setStatus(PolydockAppInstanceStatus::REMOVE_FAILED, 'Failed to remove Lagoon environment')->save();

            return $appInstance;
        }

        if (isset($removedEnvironment['deleteEnvironment']) && $removedEnvironment['deleteEnvironment'] === 'success') {
            $this->info('Environment deleted successfully', $logContext + ['removedEnvironment' => $removedEnvironment]);
        } else {
            $this->error('No error, but failed to remove Lagoon environment', $logContext + ['error' => $removedEnvironment['error']]);
            $appInstance->setStatus(PolydockAppInstanceStatus::REMOVE_FAILED, 'No error, but failed to remove Lagoon environment')->save();

            return $appInstance;
        }

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::REMOVE_COMPLETED, 'Remove completed')->save();

        return $appInstance;
    }
}
