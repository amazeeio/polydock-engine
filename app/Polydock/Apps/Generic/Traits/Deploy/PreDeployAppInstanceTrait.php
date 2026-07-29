<?php

namespace App\Polydock\Apps\Generic\Traits\Deploy;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

trait PreDeployAppInstanceTrait
{
    /**
     * Handles pre-deployment tasks for an app instance.
     *
     * This method is called before deploying the app instance. It validates the instance
     * is in the correct status, sets it to running, executes pre-deployment logic,
     * and marks it as completed.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to process
     * @return PolydockAppInstanceInterface The processed app instance
     *
     * @throws PolydockAppInstanceStatusFlowException If instance is not in PENDING_PRE_DEPLOY status
     */
    public function preDeployAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;

        return $this->runLifecyclePhase(
            $appInstance,
            $functionName,
            PolydockAppInstanceStatus::PENDING_PRE_DEPLOY,
            PolydockAppInstanceStatus::PRE_DEPLOY_RUNNING,
            PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED,
            PolydockAppInstanceStatus::PRE_DEPLOY_FAILED,
            function (PolydockAppInstanceInterface $appInstance, array $logContext) use ($functionName): ?PolydockAppInstanceInterface {
                $projectName = $appInstance->getKeyValue('lagoon-project-name');
                $projectId = $appInstance->getKeyValue('lagoon-project-id');

                $this->info($functionName.': starting for project: '.$projectName.' ('.$projectId.')', $logContext);

                // There is nothing to do here beyond checking the name and ID above

                return null;
            },
            'Pre-deploy completed',
        );
    }
}
