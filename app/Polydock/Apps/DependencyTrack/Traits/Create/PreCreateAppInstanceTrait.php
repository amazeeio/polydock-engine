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

        // Do not validate lagoon project name and ID because we are only injecting into a different project/org
        return $this->runLifecyclePhase(
            $appInstance,
            $functionName,
            PolydockAppInstanceStatus::PENDING_PRE_CREATE,
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING,
            PolydockAppInstanceStatus::PRE_CREATE_COMPLETED,
            PolydockAppInstanceStatus::PRE_CREATE_FAILED,
            function (PolydockAppInstanceInterface $appInstance, array $logContext) use ($functionName): ?PolydockAppInstanceInterface {
                $this->info("$functionName: checking for Lagoon Organisation and Project fields", $logContext);

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
                    $appInstance->setStatus(PolydockAppInstanceStatus::PRE_CREATE_FAILED, 'An exception occurred: '.$e->getMessage())->save();

                    return $appInstance;
                }

                return null;
            },
            'Pre-create completed',
            validateLagoonProjectName: false,
            validateLagoonProjectId: false,
        );
    }
}
