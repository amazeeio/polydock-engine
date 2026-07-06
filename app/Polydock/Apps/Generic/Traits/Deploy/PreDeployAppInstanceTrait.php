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
        $logContext = $this->getLogContext($functionName);
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = true;

        $this->info($functionName.': starting', $logContext);

        // Throws PolydockAppInstanceStatusFlowException
        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_PRE_DEPLOY,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $projectId = $appInstance->getKeyValue('lagoon-project-id');

        $this->info($functionName.': starting for project: '.$projectName.' ('.$projectId.')', $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::PRE_DEPLOY_RUNNING,
            PolydockAppInstanceStatus::PRE_DEPLOY_RUNNING->getStatusMessage()
        )->save();

        // There is nothing to do here beyond checking the name and ID above

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::PRE_DEPLOY_COMPLETED, 'Pre-deploy completed')->save();

        return $appInstance;
    }
}
