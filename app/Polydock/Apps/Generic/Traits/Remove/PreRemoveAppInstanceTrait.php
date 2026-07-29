<?php

namespace App\Polydock\Apps\Generic\Traits\Remove;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

trait PreRemoveAppInstanceTrait
{
    /**
     * Handles pre-removal tasks for an app instance.
     *
     * This method is called before removing the app instance. It validates the instance
     * is in the correct status, sets it to running, executes pre-removal logic,
     * and marks it as completed.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to process
     * @return PolydockAppInstanceInterface The processed app instance
     *
     * @throws PolydockAppInstanceStatusFlowException If instance is not in PENDING_PRE_REMOVE status
     */
    public function preRemoveAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;

        // Adopted (claimed) projects detach instead of being removed. Removal
        // enters the pipeline here (PENDING_PRE_REMOVE), so this guard must
        // mirror the ones in Remove/PostRemove — without it the Lagoon
        // ping/validation below runs first and a detach could never succeed
        // while Lagoon is unreachable.
        if ($appInstance->getKeyValue('adopted')) {
            $logContext = $this->getLogContext($functionName);
            $this->info($functionName.': starting', $logContext);
            $this->validateAppInstanceStatusIsExpected($appInstance, PolydockAppInstanceStatus::PENDING_PRE_REMOVE);
            $this->info($functionName.': adopted project — skipping Lagoon pre-remove checks', $logContext);
            $appInstance->setStatus(
                PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED,
                'Pre-remove skipped for adopted project (Lagoon environment left intact)'
            )->save();

            return $appInstance;
        }

        return $this->runLifecyclePhase(
            $appInstance,
            $functionName,
            PolydockAppInstanceStatus::PENDING_PRE_REMOVE,
            PolydockAppInstanceStatus::PRE_REMOVE_RUNNING,
            PolydockAppInstanceStatus::PRE_REMOVE_COMPLETED,
            PolydockAppInstanceStatus::PRE_REMOVE_FAILED,
            function (PolydockAppInstanceInterface $appInstance, array $logContext) use ($functionName): ?PolydockAppInstanceInterface {
                $projectName = $appInstance->getKeyValue('lagoon-project-name');
                $projectId = $appInstance->getKeyValue('lagoon-project-id');

                $this->info($functionName.': starting for project: '.$projectName.' ('.$projectId.')', $logContext);

                // There is nothing to do here beyond checking the name and ID above

                return null;
            },
            'Pre-remove completed',
        );
    }
}
