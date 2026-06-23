<?php

declare(strict_types=1);

namespace App\Polydock\Apps\DependencyTrack\Traits\Create;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;

trait PreCreateAppInstanceTrait
{
    public function preCreateAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;
        $logContext = $this->getLogContext($functionName);
        $testLagoonPing = true;
        // Do not validate lagoon project name and ID because we are only injecting into a different project/org
        $validateLagoonValues = true;
        $validateLagoonProjectName = false;
        $validateLagoonProjectId = false;

        $this->info("$functionName: starting", $logContext);

        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_PRE_CREATE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $this->info("$functionName: checking for Lagoon Organisation and Project fields", $logContext);

        $appInstance->setStatus(
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING,
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING->getStatusMessage()
        )->save();

        $lagoonOrgName = $appInstance->getKeyValue('lagoon_organisation');

        try {
            if (! empty($lagoonOrgName)) {
                $this->info("$functionName: Found Lagoon Organisation setting, injecting variable LAGOON_FEATURE_FLAG_INSIGHTS.", $logContext);
                $this->lagoonClient->addOrUpdateGlobalVariableForOrganization(
                    $lagoonOrgName,
                    'LAGOON_FEATURE_FLAG_INSIGHTS',
                    'enabled'
                );
            }
        } catch (\Exception $e) {
            $this->error('Pre Create Failed: '.$e->getMessage(), [
                'exception_class' => \get_class($e),
                'exception_trace' => $e->getTraceAsString(),
            ]);
            $appInstance->setStatus(PolydockAppInstanceStatus::PRE_CREATE_FAILED, 'An exception occured: '.$e->getMessage())->save();

            return $appInstance;
        }

        $this->info("$functionName: completed", $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::PRE_CREATE_COMPLETED, 'Pre-create completed')->save();

        return $appInstance;
    }
}
