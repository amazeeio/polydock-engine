<?php

declare(strict_types=1);

namespace App\Polydock\Apps\AnythingLlm;

// use Filament\Forms;
// use Filament\Infolists;
use App\Polydock\Apps\Generic\PolydockAiApp as GenericPolydockAiApp;
use App\Polydock\Core\Attributes\PolydockAppInstanceFields;
use App\Polydock\Core\Attributes\PolydockAppStoreFields;
use App\Polydock\Core\Attributes\PolydockAppTitle;
use App\Polydock\Core\Contracts\HasAppInstanceFormFields;
use App\Polydock\Core\Contracts\HasStoreAppFormFields;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;
use App\Polydock\Core\PolydockAppVariableDefinitionBase;
use Filament\Forms\Components\Component;

#[PolydockAppTitle('AnythingLLM App')]
#[PolydockAppStoreFields]
#[PolydockAppInstanceFields]
class PolydockAnythingLLMApp extends GenericPolydockAiApp implements HasAppInstanceFormFields, HasStoreAppFormFields
{
    public static string $version = '0.1.3';

    /**
     * @return array<PolydockAppVariableDefinitionBase>
     */
    public static function getAppDefaultVariableDefinitions(): array
    {
        return array_merge(parent::getAppDefaultVariableDefinitions(), [
            new PolydockAppVariableDefinitionBase('amazee-ai-backend-region-id'),
        ]);
    }

    /**
     * @return array<Component>
     */
    #[\Override]
    public static function getStoreAppFormSchema(): array
    {
        return [];
    }

    /**
     * @return array<\Filament\Infolists\Components\Component>
     */
    #[\Override]
    public static function getStoreAppInfolistSchema(): array
    {
        return [];
    }

    /**
     * @return array<Component>
     */
    #[\Override]
    public static function getAppInstanceFormSchema(): array
    {
        return [];
    }

    /**
     * @return array<\Filament\Infolists\Components\Component>
     */
    #[\Override]
    public static function getAppInstanceInfolistSchema(): array
    {
        return [];
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    #[\Override]
    public function claimAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;
        $logContext = $this->getLogContext($functionName);
        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $deployEnvironment = $appInstance->getKeyValue('lagoon-deploy-branch');

        $this->info("$functionName: starting AnythingLLM claim", $logContext);

        $this->validateAppInstanceStatusIsExpected($appInstance, PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM);

        $this->setLagoonClientFromAppInstance($appInstance);
        $this->setAmazeeAiBackendClientFromAppInstance($appInstance);

        // Generate JWT_SECRET if not already set
        $jwtSecret = $appInstance->getKeyValue('anythingllm-jwt-secret');
        if (empty($jwtSecret)) {
            $jwtSecret = bin2hex(random_bytes(32));
            $appInstance->storeKeyValue('anythingllm-jwt-secret', $jwtSecret);
            $this->info('Generated new JWT_SECRET for AnythingLLM', $logContext);
        }

        $authToken = $appInstance->getKeyValue('anythingllm-auth-token');
        if (empty($authToken)) {
            $authToken = bin2hex(random_bytes(16));
            $appInstance->storeKeyValue('anythingllm-auth-token', $authToken);
            $this->info('Generated new AUTH_TOKEN for AnythingLLM', $logContext);
        }

        // Set auth variables before the claim completes so AnythingLLM can skip onboarding.
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'JWT_SECRET', $jwtSecret, 'GLOBAL');
        sleep(1);
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'AUTH_TOKEN', $authToken, 'GLOBAL');
        sleep(1);

        // Get AI credentials from backend
        $aiCredentials = $this->getPrivateAICredentialsFromBackend($appInstance);

        // Inject AI credentials as Lagoon variables using the new naming scheme
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'LLM_PROVIDER', 'litellm', 'GLOBAL');
        sleep(1);
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'LLM_URL', $aiCredentials['litellm_api_url'], 'GLOBAL');
        sleep(1);
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'LLM_AI_KEY', $aiCredentials['litellm_token'], 'GLOBAL');
        sleep(1);
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'LITE_LLM_MODEL_PREF', 'chat', 'GLOBAL');
        sleep(1);
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'LITE_LLM_MODEL_TOKEN_LIMIT', '8192', 'GLOBAL');
        sleep(1);

        $this->addOrUpdateLagoonProjectVariable($appInstance, 'EMBEDDING_ENGINE', 'litellm', 'GLOBAL');
        sleep(1);
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'EMBEDDING_MODEL_PREF', 'embeddings', 'GLOBAL');
        sleep(1);
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'EMBEDDING_MODEL_MAX_CHUNK_LENGTH', '8192', 'GLOBAL');
        sleep(1);
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'VECTOR_DB', 'lancedb', 'GLOBAL');
        sleep(1);

        // Inject DB credentials
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'DB_HOST', $aiCredentials['database_host'], 'GLOBAL');
        sleep(1);
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'DB_USER', $aiCredentials['database_username'], 'GLOBAL');
        sleep(1);
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'DB_PASS', $aiCredentials['database_password'], 'GLOBAL');
        sleep(1);
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'DB_NAME', $aiCredentials['database_name'], 'GLOBAL');
        sleep(1);
        $this->addOrUpdateLagoonProjectVariable($appInstance, 'DB_PORT', '5432', 'GLOBAL');
        sleep(1);

        $this->info('Injected AnythingLLM auth, model, vector, and database variables', $logContext);

        $variablesOnlyDeployment = $this->lagoonClient->deployProjectEnvironmentByName(
            $projectName,
            $deployEnvironment,
            ['LAGOON_VARIABLES_ONLY' => 'true']
        );

        if (isset($variablesOnlyDeployment['error'])) {
            $errorMessage = \is_array($variablesOnlyDeployment['error'])
                ? ($variablesOnlyDeployment['error'][0]['message'] ?? json_encode($variablesOnlyDeployment['error']))
                : (string) $variablesOnlyDeployment['error'];

            throw new \Exception("Failed to trigger Lagoon variables-only deployment: {$errorMessage}");
        }

        $latestDeploymentName = $variablesOnlyDeployment['deployEnvironmentBranch'] ?? null;
        if (! empty($latestDeploymentName)) {
            $appInstance->storeKeyValue('lagoon-latest-deployment-name', $latestDeploymentName);
        }

        $this->info('Triggered Lagoon variables-only deployment for AnythingLLM claim variables', $logContext + [
            'projectName' => $projectName,
            'deployEnvironment' => $deployEnvironment,
            'deploymentName' => $latestDeploymentName,
        ]);

        return parent::claimAppInstance($appInstance);
    }
}
