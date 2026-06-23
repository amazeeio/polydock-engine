<?php

namespace App\Polydock\Apps\PrivateGpt\Traits\Create;

use App\Polydock\Apps\PrivateGpt\Generated\Routemap\Routemapper;
use App\Polydock\Apps\PrivateGpt\Interfaces\AmazeeAiOperationsInterface;
use App\Polydock\Apps\PrivateGpt\Interfaces\LagoonOperationsInterface;
use App\Polydock\Apps\PrivateGpt\Interfaces\LoggerInterface;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;

trait PostCreateAppInstanceTrait
{
    protected ?LoggerInterface $postCreateLogger = null;

    protected ?LagoonOperationsInterface $postCreateLagoonOps = null;

    protected ?AmazeeAiOperationsInterface $postCreateAmazeeAiOps = null;

    /**
     * Setup trait dependencies
     */
    public function setupPostCreateTrait(
        ?LoggerInterface $logger = null,
        ?LagoonOperationsInterface $lagoonOps = null,
        ?AmazeeAiOperationsInterface $amazeeAiOps = null
    ): void {
        $this->postCreateLogger = $logger;
        $this->postCreateLagoonOps = $lagoonOps;
        $this->postCreateAmazeeAiOps = $amazeeAiOps;
    }

    /**
     * Ensure trait is initialized with dependencies
     */
    private function ensurePostCreateTraitInitialized(): void
    {
        if ($this->postCreateLogger === null && $this instanceof LoggerInterface) {
            $this->postCreateLogger = $this;
        }
        if ($this->postCreateLagoonOps === null && $this instanceof LagoonOperationsInterface) {
            $this->postCreateLagoonOps = $this;
        }
        if ($this->postCreateAmazeeAiOps === null && $this instanceof AmazeeAiOperationsInterface) {
            $this->postCreateAmazeeAiOps = $this;
        }
    }

    protected function generateSecurePassword(int $length = 32): string
    {
        // Generate a secure random password using URL-safe base64 encoding
        $password = rtrim(strtr(base64_encode(random_bytes(max(1, $length))), '+/', '-_'), '=');

        return substr($password, 0, $length);
    }

    public function postCreateAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->ensurePostCreateTraitInitialized();

        $functionName = __FUNCTION__;
        $logContext = $this->postCreateLogger?->getLogContext($functionName) ?? [];
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = true;

        $this->postCreateLogger?->info($functionName.': starting', $logContext);

        $this->postCreateLagoonOps?->validateAndSetupLagoon(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_POST_CREATE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $this->postCreateAmazeeAiOps?->setAmazeeAiClientFromAppInstance($appInstance);

        $projectName = $appInstance->getKeyValue('lagoon-project-name');

        $this->postCreateLogger?->info($functionName.': starting for project: '.$projectName, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::POST_CREATE_RUNNING,
            PolydockAppInstanceStatus::POST_CREATE_RUNNING->getStatusMessage()
        )->save();

        try {
            if ($this->lagoonClient) {
                $addGroupToProjectResult = $this->lagoonClient->addGroupToProject(
                    $appInstance->getKeyValue('lagoon-deploy-group-name'),
                    $projectName
                );

                if (isset($addGroupToProjectResult['error'])) {
                    $this->postCreateLogger?->error($addGroupToProjectResult['error'][0]['message'], $logContext);
                    throw new \Exception($addGroupToProjectResult['error'][0]['message']);
                }

                if (! isset($addGroupToProjectResult['addGroupsToProject']) || ! isset($addGroupToProjectResult['addGroupsToProject']['id'])) {
                    $this->postCreateLogger?->error('addGroupsToProject ID not found in data', $logContext);
                    throw new \Exception('addGroupsToProject ID not found in data');
                }
            }

            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_NAME', $appInstance->getApp()->getAppName(), 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_GENERATED_APP_ADMIN_USERNAME', $appInstance->getKeyValue('lagoon-generate-app-admin-username'), 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_GENERATED_APP_ADMIN_PASSWORD', $appInstance->getKeyValue('lagoon-generate-app-admin-password'), 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_USER_FIRST_NAME', $appInstance->getKeyValue('user-first-name'), 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_USER_LAST_NAME', $appInstance->getKeyValue('user-last-name'), 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_USER_EMAIL', $appInstance->getKeyValue('user-email'), 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_INSTANCE_HEALTH_WEBHOOK_URL', $appInstance->getKeyValue('polydock-app-instance-health-webhook-url'), 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'LAGOON_FEATURE_FLAG_INSIGHTS', 'false', 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'REGISTRY_ghcr_USERNAME', $appInstance->getKeyValue('amazee-ai-registry-ghcr-username'), 'CONTAINER_REGISTRY');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'REGISTRY_ghcr_PASSWORD', $appInstance->getKeyValue('amazee-ai-registry-ghcr-password'), 'CONTAINER_REGISTRY');

            // Set the user's selected region information from the store
            /** @phpstan-ignore-next-line */
            $storeName = $appInstance->storeApp->store->name;
            /** @phpstan-ignore-next-line */
            $storeId = $appInstance->storeApp->store->id;
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_USER_SELECTED_REGION_NAME', $storeName, 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_USER_SELECTED_REGION_ID', $storeId, 'GLOBAL');

            sleep(2);
            $this->postCreateLogger?->info($functionName.': injecting amazee.ai direct API credentials', $logContext);

            $amazeeAiBackendToken = $appInstance->getKeyValue('amazee-ai-backend-token');
            // $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'AMAZEE_AI_BACKEND_TOKEN', $amazeeAiBackendToken, 'GLOBAL');

            $teamId = $appInstance->getKeyValue('amazee-ai-team-id');
            if ($teamId) {
                $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'AMAZEE_AI_TEAM_ID', $teamId, 'GLOBAL');
            }

            // AMAZEE_AI_DEFAULT_REGION_ID - this will be the storeId specified at the app level.
            $aiBackendRegionId = $appInstance->getKeyValue('amazee-ai-backend-region-id');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'AMAZEE_AI_DEFAULT_REGION_ID', $aiBackendRegionId, 'GLOBAL');

            $teamCredentials = $appInstance->getKeyValue('amazee-ai-team-credentials');
            // These seem to be the keys injected from the amazee.ai operations
            if ($teamCredentials) {
                $credentials = json_decode($teamCredentials, true);
                if (isset($credentials['llm_key']) && isset($credentials['llm_key']['litellm_token'])) {
                    $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'AMAZEEAI_API_KEY', $credentials['llm_key']['litellm_token'], 'GLOBAL');
                }
                if (isset($credentials['llm_key']) && isset($credentials['llm_key']['litellm_api_url'])) {
                    $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'AMAZEEAI_BASE_URL', $credentials['llm_key']['litellm_api_url'], 'GLOBAL');
                }
                if (isset($credentials['backend_key']) && isset($credentials['backend_key']['token'])) {
                    $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'AMAZEE_AI_BACKEND_TOKEN', $credentials['backend_key']['token'], 'GLOBAL');
                } else {
                    throw new \RuntimeException('No backend_key token found in amazee-ai-team-credentials');
                }
            }

            // Chainlit details - seems to be hardcoded
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'OAUTH_DRUPAL_CLIENT_ID', 'chainlit', 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'CHAINLIT_AUTH_SECRET', $this->generateSecurePassword(64), 'GLOBAL');

            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'MCP_AUTH_SECRET', $this->generateSecurePassword(64), 'GLOBAL');

            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'OAUTH_DRUPAL_CLIENT_SECRET', $this->generateSecurePassword(64), 'GLOBAL');

            // Phoenix details
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'PHOENIX_API_KEY', $appInstance->getKeyValue('amazee-ai-phoenix-api-key'), 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'PHOENIX_COLLECTOR_ENDPOINT', $appInstance->getKeyValue('amazee-ai-phoenix-collector-endpoint'), 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'PHOENIX_PROJECT_NAME', $projectName, 'GLOBAL');

            // Unleash Values
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'UNLEASH_API_TOKEN', $appInstance->getKeyValue('amazee-ai-unleash-api-token'), 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'UNLEASH_URL', $appInstance->getKeyValue('amazee-ai-unleash-url'), 'GLOBAL');

            // Now let's create the routes
            $projectName = $appInstance->getKeyValue('lagoon-project-name');
            $deployTargetId = (int) $appInstance->getKeyValue('lagoon-deploy-region-id');
            $drupalUrl = 'https://'.Routemapper::drupalUrl($deployTargetId, $projectName);
            $chatUrl = 'https://'.Routemapper::chainlitUrl($deployTargetId, $projectName);
            $routesBase64 = Routemapper::base64encodedRoutes($deployTargetId, $projectName);
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'LAGOON_ROUTES_JSON', $routesBase64, 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'DRUPAL_URL', $drupalUrl, 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'CHAINLIT_URL', $chatUrl, 'GLOBAL');

            // Inject drupal defaults

            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'DRUPAL_ADMIN_USER', $appInstance->getKeyValue('user-email'), 'GLOBAL');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'DRUPAL_ADMIN_PASSWORD', $appInstance->getKeyValue('user-password'), 'GLOBAL');

            // Inject company name
            $companyName = $appInstance->getKeyValue('company-name');
            $this->postCreateLagoonOps?->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_COMPANY_NAME', $companyName, 'GLOBAL');

            $this->postCreateLogger?->info($functionName.': completed injecting amazee.ai direct API credentials', $logContext);

        } catch (\Exception $e) {
            $this->postCreateLogger?->error('Post Create Failed: '.$e->getMessage(), $logContext);
            $appInstance->setStatus(PolydockAppInstanceStatus::POST_CREATE_FAILED, 'An exception occurred: '.$e->getMessage())->save();

            return $appInstance;
        }

        $this->postCreateLogger?->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::POST_CREATE_COMPLETED, 'Post-create completed')->save();

        return $appInstance;
    }
}
