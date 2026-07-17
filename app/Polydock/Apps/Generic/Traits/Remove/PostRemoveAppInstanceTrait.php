<?php

namespace App\Polydock\Apps\Generic\Traits\Remove;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

trait PostRemoveAppInstanceTrait
{
    /**
     * Handles post-removal tasks for an app instance.
     *
     * This method is called after removing the app instance. It validates the instance
     * is in the correct status, sets it to running, executes post-removal logic,
     * and marks it as completed.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to process
     * @return PolydockAppInstanceInterface The processed app instance
     *
     * @throws PolydockAppInstanceStatusFlowException If instance is not in PENDING_POST_REMOVE status
     */
    public function postRemoveAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;

        // Adopted (claimed) projects are detached, not destroyed. Skip the
        // post-remove markers entirely — writing POLYDOCK_APP_REMOVED_* onto the
        // still-live Lagoon project would mutate an environment we promised to
        // leave intact. Guard runs before the Lagoon ping/validation so it never
        // touches Lagoon.
        if ($appInstance->getKeyValue('adopted')) {
            $logContext = $this->getLogContext($functionName);
            $this->validateAppInstanceStatusIsExpected($appInstance, PolydockAppInstanceStatus::PENDING_POST_REMOVE);
            $this->info($functionName.': adopted project — skipping Lagoon post-remove markers', $logContext);
            $appInstance->setStatus(
                PolydockAppInstanceStatus::POST_REMOVE_COMPLETED,
                'Post-remove skipped for adopted project (Lagoon environment left intact)'
            )->save();

            return $appInstance;
        }

        return $this->runLifecyclePhase(
            $appInstance,
            $functionName,
            PolydockAppInstanceStatus::PENDING_POST_REMOVE,
            PolydockAppInstanceStatus::POST_REMOVE_RUNNING,
            PolydockAppInstanceStatus::POST_REMOVE_COMPLETED,
            PolydockAppInstanceStatus::POST_REMOVE_FAILED,
            function (PolydockAppInstanceInterface $appInstance, array $logContext) use ($functionName): ?PolydockAppInstanceInterface {
                $projectName = $appInstance->getKeyValue('lagoon-project-name');

                $this->info($functionName.': starting for project: '.$projectName, $logContext);

                try {
                    $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_REMOVED_DATE', date('Y-m-d'), 'GLOBAL');
                    $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_REMOVED_TIME', date('H:i:s'), 'GLOBAL');
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                    $appInstance->setStatus(PolydockAppInstanceStatus::POST_REMOVE_FAILED, $e->getMessage())->save();

                    return $appInstance;
                }

                return null;
            },
            'Post-remove completed',
        );
    }
}
