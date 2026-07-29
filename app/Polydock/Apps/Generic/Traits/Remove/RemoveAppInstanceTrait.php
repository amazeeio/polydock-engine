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

        // Adopted (claimed) projects are pre-existing environments Polydock did
        // not create. Removing one means detaching: drop the Polydock record but
        // leave the running Lagoon environment intact — never delete it, and
        // never touch Lagoon. This guard runs before the Lagoon ping/validation
        // below so a detach succeeds even when Lagoon is unreachable.
        if ($appInstance->getKeyValue('adopted')) {
            $logContext = $this->getLogContext($functionName);
            $this->info($functionName.': starting', $logContext);
            $this->validateAppInstanceStatusIsExpected($appInstance, PolydockAppInstanceStatus::PENDING_REMOVE);
            $this->info($functionName.': adopted project — detaching, leaving Lagoon environment intact', $logContext);
            $appInstance->setStatus(
                PolydockAppInstanceStatus::REMOVE_COMPLETED,
                'Adopted project detached (Lagoon environment left intact)'
            )->save();

            return $appInstance;
        }

        return $this->runLifecyclePhase(
            $appInstance,
            $functionName,
            PolydockAppInstanceStatus::PENDING_REMOVE,
            PolydockAppInstanceStatus::REMOVE_RUNNING,
            PolydockAppInstanceStatus::REMOVE_COMPLETED,
            PolydockAppInstanceStatus::REMOVE_FAILED,
            function (PolydockAppInstanceInterface $appInstance, array $logContext) use ($functionName): ?PolydockAppInstanceInterface {
                $projectName = $appInstance->getKeyValue('lagoon-project-name');
                $deployEnvironment = $appInstance->getKeyValue('lagoon-deploy-branch');
                $logContext['projectName'] = $projectName;
                $logContext['deployEnvironment'] = $deployEnvironment;

                $this->info($functionName.': starting for project: '.$projectName, $logContext);

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

                return null;
            },
            'Remove completed',
        );
    }
}
