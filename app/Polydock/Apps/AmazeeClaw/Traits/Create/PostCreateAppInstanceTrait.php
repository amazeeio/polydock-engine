<?php

namespace App\Polydock\Apps\AmazeeClaw\Traits\Create;

use App\Polydock\Apps\AmazeeClaw\Enums\AmazeeAiKeyMode;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;

trait PostCreateAppInstanceTrait
{
    public function postCreateAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;

        return $this->runLifecyclePhase(
            $appInstance,
            $functionName,
            PolydockAppInstanceStatus::PENDING_POST_CREATE,
            PolydockAppInstanceStatus::POST_CREATE_RUNNING,
            PolydockAppInstanceStatus::POST_CREATE_COMPLETED,
            PolydockAppInstanceStatus::POST_CREATE_FAILED,
            function (PolydockAppInstanceInterface $appInstance, array $logContext) use ($functionName): ?PolydockAppInstanceInterface {
                $projectName = $appInstance->getKeyValue('lagoon-project-name');

                $this->info("{$functionName}: starting for project: {$projectName}", $logContext);

                try {
                    $this->addDeployGroupToLagoonProject($appInstance);

                    $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_NAME', $appInstance->getApp()->getAppName(), 'GLOBAL');
                    $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_USER_EMAIL', $appInstance->getKeyValue('user-email'), 'GLOBAL');
                    $this->addOrUpdateLagoonProjectVariable($appInstance, 'LAGOON_FEATURE_FLAG_INSIGHTS', 'false', 'GLOBAL');

                    $amazeeClawDefaultModel = $this->resolveAmazeeAiDefaultModelFromInstanceOrApp($appInstance);
                    if ($amazeeClawDefaultModel !== '') {
                        $this->addOrUpdateLagoonProjectVariable($appInstance, 'AMAZEEAI_DEFAULT_MODEL', $amazeeClawDefaultModel, 'GLOBAL');
                    }

                    // AI credentials configuration. Anonymous keys are generated now;
                    // user-scoped keys are generated at claim time (see the claim trait),
                    // when the claiming user's email is known.
                    if ($this->getRequiresAiInfrastructure()) {
                        $keyMode = $this->resolveAmazeeAiKeyMode($appInstance);
                        if ($keyMode === AmazeeAiKeyMode::Anonymous) {
                            $this->info("{$functionName}: Auto-generating anonymous AI keys via amazee.ai API", $logContext);
                            $this->generateAndStoreAmazeeAiCredentials($appInstance, $logContext);
                        }
                        // User-mode credentials don't exist until claim, so there is
                        // nothing to inject yet — skip to avoid a spurious "no
                        // auto-generated credentials" warning on every post-create.
                        if ($keyMode !== AmazeeAiKeyMode::User) {
                            $this->provisionAndInjectManualAmazeeAiCredentials($appInstance, $logContext);
                        }
                    }
                } catch (\Exception $e) {
                    $this->error('Post Create Failed: '.$e->getMessage(), [
                        'exception_class' => \get_class($e),
                        'exception_trace' => $e->getTraceAsString(),
                    ]);

                    $appInstance->setStatus(PolydockAppInstanceStatus::POST_CREATE_FAILED, 'An exception occurred: '.$e->getMessage())->save();

                    return $appInstance;
                }

                return null;
            },
            'Post-create completed',
        );
    }
}
