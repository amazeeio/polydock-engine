<?php

namespace App\Polydock\Apps\Generic\Traits\Create;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

trait PreCreateAppInstanceTrait
{
    use ResolvesCustomProjectNameTrait;

    /**
     * Handles pre-creation tasks for an app instance.
     *
     * This method is called before creating the app instance. It validates the instance
     * is in the correct status, sets it to running, executes pre-creation logic,
     * and marks it as completed.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to process
     * @return PolydockAppInstanceInterface The processed app instance
     *
     * @throws PolydockAppInstanceStatusFlowException If instance is not in PENDING_PRE_CREATE status
     */
    public function preCreateAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;

        return $this->runLifecyclePhase(
            $appInstance,
            $functionName,
            PolydockAppInstanceStatus::PENDING_PRE_CREATE,
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING,
            PolydockAppInstanceStatus::PRE_CREATE_COMPLETED,
            PolydockAppInstanceStatus::PRE_CREATE_FAILED,
            function (PolydockAppInstanceInterface $appInstance, array $logContext) use ($functionName): ?PolydockAppInstanceInterface {
                // While we don't use this here, lets make sure we have it available for later
                if ($this->getRequiresAiInfrastructure()) {
                    // Throws PolydockAppInstanceStatusFlowException
                    $this->setAmazeeAiBackendClientFromAppInstance($appInstance);
                }

                // Apps configured for custom naming may carry an externally supplied
                // name - enforce the prefix, sanitize it, and dedupe against Lagoon.
                $this->finalizeCustomProjectNameIfConfigured($appInstance);

                $projectName = $appInstance->getKeyValue('lagoon-project-name');

                $this->info($functionName.': starting for project: '.$projectName, $logContext);

                return null;
            },
            'Pre-create completed',
            validateLagoonProjectId: false,
        );
    }
}
