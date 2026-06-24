<?php

namespace App\Polydock\Apps\AmazeeClaw\Traits\Create;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;

trait PostCreateAppInstanceTrait
{
    public function postCreateAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;
        $logContext = $this->getLogContext($functionName);
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = true;

        $this->info("{$functionName}: starting", $logContext);

        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_POST_CREATE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');

        $this->info("{$functionName}: starting for project: {$projectName}", $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::POST_CREATE_RUNNING,
            PolydockAppInstanceStatus::POST_CREATE_RUNNING->getStatusMessage()
        )->save();

        try {
            $addGroupToProjectResult = $this->lagoonClient->addGroupToProject(
                $appInstance->getKeyValue('lagoon-deploy-group-name'),
                $projectName
            );

            if (isset($addGroupToProjectResult['error'])) {
                $errorMessage = \is_array($addGroupToProjectResult['error'])
                    ? ($addGroupToProjectResult['error'][0]['message'] ?? json_encode($addGroupToProjectResult['error']))
                    : $addGroupToProjectResult['error'];
                $this->error($errorMessage);
                throw new \Exception($errorMessage);
            }

            if (! isset($addGroupToProjectResult['addGroupsToProject']) || ! isset($addGroupToProjectResult['addGroupsToProject']['id'])) {
                $this->error('addGroupsToProject ID not found in data');
                throw new \Exception('addGroupsToProject ID not found in data');
            }

            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_NAME', $appInstance->getApp()->getAppName(), 'GLOBAL');
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_USER_EMAIL', $appInstance->getKeyValue('user-email'), 'GLOBAL');
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'LAGOON_FEATURE_FLAG_INSIGHTS', 'false', 'GLOBAL');

            $amazeeClawDefaultModel = '';
            if (method_exists($appInstance, 'getPolydockVariableValue')) {
                /** @phpstan-ignore-next-line */
                $amazeeClawDefaultModel = (string) ($appInstance->getPolydockVariableValue('instance_config_openclaw_default_model') ?? '');
            }
            if ($amazeeClawDefaultModel === '') {
                $amazeeClawDefaultModel = $appInstance->getKeyValue('instance_config_openclaw_default_model');
            }
            if ($amazeeClawDefaultModel === '') {
                $amazeeClawDefaultModel = $appInstance->getKeyValue('app_config_openclaw_default_model');
            }
            if ($amazeeClawDefaultModel === '') {
                /** @phpstan-ignore-next-line */
                $storeAppConfig = (array) (($appInstance->storeApp->app_config ?? null) ?: []);
                $amazeeClawDefaultModel = (string) ($storeAppConfig['openclaw_default_model'] ?? '');
            }
            if ($amazeeClawDefaultModel !== '') {
                $this->addOrUpdateLagoonProjectVariable($appInstance, 'AMAZEEAI_DEFAULT_MODEL', $amazeeClawDefaultModel, 'GLOBAL');
            }

            // AI credentials configuration
            if ($this->getRequiresAiInfrastructure()) {
                $keyMode = $this->resolveAmazeeAiKeyMode($appInstance);
                if ($keyMode === 'auto') {
                    $this->info("{$functionName}: Auto-generating AI keys via amazee.ai API", $logContext);
                    $this->setAmazeeAiBackendClientFromAppInstance($appInstance);
                    $privateAiCredentials = $this->getPrivateAICredentialsFromBackend($appInstance);
                    $appInstance->storeKeyValue('amazee-ai-generated-credentials', json_encode($privateAiCredentials) ?: '');
                    $appInstance->save();
                }
                $this->provisionAndInjectManualAmazeeAiCredentials($appInstance, $logContext);
            }
        } catch (\Exception $e) {
            $this->error('Post Create Failed: '.$e->getMessage(), [
                'exception_class' => \get_class($e),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            $appInstance->setStatus(PolydockAppInstanceStatus::POST_CREATE_FAILED, 'An exception occurred: '.$e->getMessage())->save();

            return $appInstance;
        }

        $this->info("{$functionName}: completed", $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::POST_CREATE_COMPLETED, 'Post-create completed')->save();

        return $appInstance;
    }
}
