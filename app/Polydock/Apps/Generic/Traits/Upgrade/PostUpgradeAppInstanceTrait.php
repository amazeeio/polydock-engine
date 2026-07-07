<?php

namespace App\Polydock\Apps\Generic\Traits\Upgrade;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;
use Exception;

trait PostUpgradeAppInstanceTrait
{
    /**
     * Handles post-upgrade tasks for an app instance.
     *
     * This method is called after upgrading the app instance. It validates the instance
     * is in the correct status, sets it to running, executes post-upgrade logic,
     * and marks it as completed.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to process
     * @return PolydockAppInstanceInterface The processed app instance
     *
     * @throws PolydockAppInstanceStatusFlowException If instance is not in PENDING_POST_UPGRADE status
     */
    public function postUpgradeAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
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
            PolydockAppInstanceStatus::PENDING_POST_UPGRADE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');

        $this->info($functionName.': starting for project: '.$projectName, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::POST_UPGRADE_RUNNING,
            PolydockAppInstanceStatus::POST_UPGRADE_RUNNING->getStatusMessage()
        )->save();

        $appInstance->warning('TODO: Implement post-upgrade logic', $logContext);
        try {
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_LAST_UPGRADED_DATE', date('Y-m-d'), 'GLOBAL');
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_LAST_UPGRADED_TIME', date('H:i:s'), 'GLOBAL');
        } catch (Exception $e) {
            $this->error($e->getMessage());
            $appInstance->setStatus(PolydockAppInstanceStatus::POST_UPGRADE_FAILED, $e->getMessage())->save();

            return $appInstance;
        }

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED, 'Post-upgrade completed')->save();

        return $appInstance;
    }
}
