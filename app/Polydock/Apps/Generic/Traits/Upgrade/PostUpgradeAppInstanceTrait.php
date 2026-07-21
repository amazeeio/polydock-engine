<?php

namespace App\Polydock\Apps\Generic\Traits\Upgrade;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

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

        return $this->runLifecyclePhase(
            $appInstance,
            $functionName,
            PolydockAppInstanceStatus::PENDING_POST_UPGRADE,
            PolydockAppInstanceStatus::POST_UPGRADE_RUNNING,
            PolydockAppInstanceStatus::POST_UPGRADE_COMPLETED,
            PolydockAppInstanceStatus::POST_UPGRADE_FAILED,
            function (PolydockAppInstanceInterface $appInstance, array $logContext) use ($functionName): ?PolydockAppInstanceInterface {
                $projectName = $appInstance->getKeyValue('lagoon-project-name');

                $this->info($functionName.': starting for project: '.$projectName, $logContext);

                $appInstance->warning('TODO: Implement post-upgrade logic', $logContext);
                try {
                    $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_LAST_UPGRADED_DATE', date('Y-m-d'), 'GLOBAL');
                    $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_LAST_UPGRADED_TIME', date('H:i:s'), 'GLOBAL');
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                    $appInstance->setStatus(PolydockAppInstanceStatus::POST_UPGRADE_FAILED, $e->getMessage())->save();

                    return $appInstance;
                }

                return null;
            },
            'Post-upgrade completed',
        );
    }
}
