<?php

namespace App\Polydock\Apps\Generic\Traits\Create;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

trait PostCreateAppInstanceTrait
{
    use InjectsLagoonCustomRouteTrait;

    /**
     * Handles post-creation tasks for an app instance.
     *
     * This method is called after creating the app instance. It validates the instance
     * is in the correct status, sets it to running, executes post-creation logic,
     * and marks it as completed.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to process
     * @return PolydockAppInstanceInterface The processed app instance
     *
     * @throws PolydockAppInstanceStatusFlowException If instance is not in PENDING_POST_CREATE status
     */
    public function postCreateAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;
        $logContext = $this->getLogContext($functionName);
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = true;

        $this->info($functionName.': starting', $logContext);

        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_POST_CREATE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        if ($this->getRequiresAiInfrastructure()) {
            $this->setAmazeeAiBackendClientFromAppInstance($appInstance);
        }

        $projectName = $appInstance->getKeyValue('lagoon-project-name');

        $this->info($functionName.': starting for project: '.$projectName, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::POST_CREATE_RUNNING,
            PolydockAppInstanceStatus::POST_CREATE_RUNNING->getStatusMessage()
        )->save();

        try {
            $this->addDeployGroupToLagoonProject($appInstance);

            //            $this->addOrUpdateLagoonProjectVariable($appInstance, "POLYDOCK_APP_CREATED_DATE", date('Y-m-d'), "GLOBAL");
            //            $this->addOrUpdateLagoonProjectVariable($appInstance, "POLYDOCK_APP_CREATED_TIME", date('H:i:s'), "GLOBAL");
            //            $this->addOrUpdateLagoonProjectVariable($appInstance, "POLYDOCK_APP_TYPE", $appInstance->getAppType(), "GLOBAL");
            //            $this->addOrUpdateLagoonProjectVariable($appInstance, "POLYDOCK_APP_VERSION", self::getAppVersion(), "GLOBAL");
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_NAME', $appInstance->getApp()->getAppName(), 'GLOBAL');
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_GENERATED_APP_ADMIN_USERNAME', $appInstance->getKeyValue('lagoon-generate-app-admin-username'), 'GLOBAL');
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_GENERATED_APP_ADMIN_PASSWORD', $appInstance->getKeyValue('lagoon-generate-app-admin-password'), 'GLOBAL');
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_USER_FIRST_NAME', $appInstance->getKeyValue('user-first-name'), 'GLOBAL');
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_USER_LAST_NAME', $appInstance->getKeyValue('user-last-name'), 'GLOBAL');
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_USER_EMAIL', $appInstance->getKeyValue('user-email'), 'GLOBAL');
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_INSTANCE_HEALTH_WEBHOOK_URL', $appInstance->getKeyValue('polydock-app-instance-health-webhook-url'), 'GLOBAL');
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'LAGOON_FEATURE_FLAG_INSIGHTS', 'false', 'GLOBAL');

            $this->addLagoonCustomRouteVariable($appInstance, $projectName, $logContext);

            // Sync project metadata to Lagoon
            $email = $appInstance->getKeyValue('user-email');
            $firstName = $appInstance->getKeyValue('user-first-name');
            $lastName = $appInstance->getKeyValue('user-last-name');

            $productType = $appInstance->getKeyValue('product-type') ?: 'generic';
            if ($productType === 'generic' && method_exists($appInstance, 'storeApp') && $appInstance->storeApp) {
                if (isset($appInstance->storeApp->productType) && $appInstance->storeApp->productType) {
                    $productType = $appInstance->storeApp->productType->slug;
                }
            }

            $lagoonEnv = config('polydock.lagoon_environment_type', 'development');
            $polydockEnv = ($lagoonEnv === 'production' || config('app.env') === 'production') ? 'prod' : 'dev';

            $metadataPayload = array_filter([
                'email' => $email,
                'product-type' => $productType,
                'firstname' => $firstName,
                'lastname' => $lastName,
                'polydock-env' => $polydockEnv,
            ]);

            foreach ($metadataPayload as $metadataKey => $metadataValue) {
                $this->info("Writing project metadata: {$metadataKey} => {$metadataValue}", $logContext);
                $metadataResult = $this->lagoonClient->updateProjectMetadata($projectName, $metadataKey, (string) $metadataValue);
                if (isset($metadataResult['error'])) {
                    $this->warning("Failed to write metadata '{$metadataKey}': ".json_encode($metadataResult['error']), $logContext);
                }
            }

            if ($this->getRequiresAiInfrastructure()) {
                $privateAiCredentials = $this->getPrivateAICredentialsFromBackend($appInstance);
                $llmApiUrl = $privateAiCredentials['litellm_api_url'];
                $llmApiHostname = preg_replace('#^https?://|/.*$#', '', (string) $llmApiUrl);

                $this->info("{$functionName}: app requires AI infrastructure", $logContext);
                $this->addOrUpdateLagoonProjectVariable($appInstance, 'AI_REGION', $privateAiCredentials['region'], 'GLOBAL');

                $this->info("{$functionName}: Injecting AI DB Credentials", $logContext);
                $this->addOrUpdateLagoonProjectVariable($appInstance, 'AI_DB_HOST_NAME', $privateAiCredentials['database_host'], 'GLOBAL');
                $this->addOrUpdateLagoonProjectVariable($appInstance, 'AI_DB_NAME', $privateAiCredentials['database_name'], 'GLOBAL');
                $this->addOrUpdateLagoonProjectVariable($appInstance, 'AI_DB_USERNAME', $privateAiCredentials['database_username'], 'GLOBAL');
                $this->addOrUpdateLagoonProjectVariable($appInstance, 'AI_DB_PASSWORD', $privateAiCredentials['database_password'], 'GLOBAL');

                $this->info("{$functionName}: Injecting AI LLM Credentials", $logContext);
                $this->addOrUpdateLagoonProjectVariable($appInstance, 'AI_LLM_API_URL', $privateAiCredentials['litellm_api_url'], 'GLOBAL');
                $this->addOrUpdateLagoonProjectVariable($appInstance, 'AI_LLM_API_HOSTNAME', $llmApiHostname, 'GLOBAL');
                $this->addOrUpdateLagoonProjectVariable($appInstance, 'AI_LLM_API_HOST_NAME', $llmApiHostname, 'GLOBAL');
                $this->addOrUpdateLagoonProjectVariable($appInstance, 'AI_LLM_API_TOKEN', $privateAiCredentials['litellm_token'], 'GLOBAL');
                $this->info("{$functionName}: Done injecting AI infrastructure", $logContext);
            }

        } catch (\Exception $e) {
            $this->error('Post Create Failed: '.$e->getMessage(), [
                'exception_class' => get_class($e),
                'exception_trace' => $e->getTraceAsString(),
            ]);

            $appInstance->setStatus(PolydockAppInstanceStatus::POST_CREATE_FAILED, 'An exception occurred: '.$e->getMessage())->save();

            return $appInstance;
        }

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::POST_CREATE_COMPLETED, 'Post-create completed')->save();

        return $appInstance;
    }
}
