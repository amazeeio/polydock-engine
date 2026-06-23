<?php

namespace App\Polydock\Apps\PrivateGpt;

use App\Polydock\Apps\Generic\Traits\Claim\ClaimAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Deploy\DeployAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Deploy\PollDeployProgressAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Deploy\PostDeployAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Deploy\PreDeployAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Health\PollHealthProgressAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Remove\PostRemoveAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Remove\PreRemoveAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Remove\RemoveAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Upgrade\PollUpgradeProgressAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Upgrade\PostUpgradeAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Upgrade\PreUpgradeAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Upgrade\UpgradeAppInstanceTrait;
use App\Polydock\Apps\PrivateGpt\Interfaces\AmazeeAiOperationsInterface;
use App\Polydock\Apps\PrivateGpt\Interfaces\LagoonOperationsInterface;
use App\Polydock\Apps\PrivateGpt\Interfaces\LoggerInterface;
use App\Polydock\Apps\PrivateGpt\Traits\Create\CreateAppInstanceTrait;
use App\Polydock\Apps\PrivateGpt\Traits\Create\PostCreateAppInstanceTrait;
use App\Polydock\Apps\PrivateGpt\Traits\Create\PreCreateAppInstanceTrait;
use App\Polydock\Apps\PrivateGpt\Traits\UsesAmazeeAiDevmode;
use App\Polydock\Clients\Lagoon\Client as LagoonClient;
use App\Polydock\Core\Attributes\PolydockAppInstanceFields;
use App\Polydock\Core\Attributes\PolydockAppStoreFields;
use App\Polydock\Core\Attributes\PolydockAppTitle;
use App\Polydock\Core\Contracts\HasAppInstanceFormFields;
use App\Polydock\Core\Contracts\HasStoreAppFormFields;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppBase;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;
use App\Polydock\Core\PolydockAppVariableDefinitionBase;
use App\Polydock\Core\PolydockEngineInterface;

#[PolydockAppTitle('Private GPT App')]
#[PolydockAppStoreFields]
#[PolydockAppInstanceFields]
class PolydockPrivateGptApp extends PolydockAppBase implements AmazeeAiOperationsInterface, HasAppInstanceFormFields, HasStoreAppFormFields, LagoonOperationsInterface, LoggerInterface
{
    // Claim
    use ClaimAppInstanceTrait;
    use CreateAppInstanceTrait;
    use DeployAppInstanceTrait;
    use PollDeployProgressAppInstanceTrait;

    // Health
    use PollHealthProgressAppInstanceTrait;
    use PollUpgradeProgressAppInstanceTrait;
    use PostCreateAppInstanceTrait;
    use PostDeployAppInstanceTrait;
    use PostRemoveAppInstanceTrait;
    use PostUpgradeAppInstanceTrait;

    // Create
    use PreCreateAppInstanceTrait;

    // Deploy
    use PreDeployAppInstanceTrait;

    // Remove
    use PreRemoveAppInstanceTrait;

    // Upgrade
    use PreUpgradeAppInstanceTrait;
    use RemoveAppInstanceTrait;
    use UpgradeAppInstanceTrait;
    use UsesAmazeeAiDevmode;

    protected bool $requiresAiInfrastructure = true;

    public static string $version = '0.0.1';

    protected ?LagoonClient $lagoonClient = null;

    protected PolydockEngineInterface $engine;

    // TODO BMK fix this type hinting
    // protected LagoonClientProviderInterface $lagoonClientProvider;
    /** @phpstan-ignore-next-line */
    protected $lagoonClientProvider;

    /**
     * @return array<int, PolydockAppVariableDefinitionBase>
     */
    public static function getAppDefaultVariableDefinitions(): array
    {
        return [
            new PolydockAppVariableDefinitionBase('lagoon-deploy-git'),
            new PolydockAppVariableDefinitionBase('lagoon-deploy-branch'),
            new PolydockAppVariableDefinitionBase('lagoon-deploy-region-id'),
            new PolydockAppVariableDefinitionBase('lagoon-deploy-private-key'),
            new PolydockAppVariableDefinitionBase('lagoon-deploy-organization-id'),
            new PolydockAppVariableDefinitionBase('lagoon-deploy-project-prefix'),
            new PolydockAppVariableDefinitionBase('lagoon-project-name'),
            new PolydockAppVariableDefinitionBase('lagoon-deploy-group-name'),
            new PolydockAppVariableDefinitionBase('amazee-ai-backend-token'),
            new PolydockAppVariableDefinitionBase('amazee-ai-backend-url'),
            new PolydockAppVariableDefinitionBase('amazee-ai-admin-email'),
            // new PolydockAppVariableDefinitionBase('amazee-ai-in-dev-mode'),
        ];
    }

    public static function getAppVersion(): string
    {
        return self::$version;
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function pingLagoonAPI(): bool
    {
        if (! $this->lagoonClient) {
            throw new PolydockAppInstanceStatusFlowException('Lagoon client not found for ping');
        }

        try {
            $ping = $this->lagoonClient->pingLagoonAPI();

            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon API ping', ['ping' => $ping]);
            }

            return $ping;
        } catch (\Exception $e) {
            throw new PolydockAppInstanceStatusFlowException('Error pinging Lagoon API: '.$e->getMessage());
        }
    }

    public function setLagoonClientFromAppInstance(PolydockAppInstanceInterface $appInstance): void
    {
        $engine = $appInstance->getEngine();
        $this->engine = $engine;

        // Setup trait dependencies

        // Here we check if we're in dev mode ...
        if ($appInstance->getKeyValue('amazee-ai-in-dev-mode') === 'true') {
            $this->debug('App instance indicates Amazee AI client should be in dev mode');
            $this->setAmazeeAiClientDevMode();
        } else {
            $this->debug('App instance indicates Amazee AI client should NOT be in dev mode');
        }

        $this->setupAmazeeAiTrait($this);

        $this->setupCreateTrait($this, $this, $this);
        $this->setupPreCreateTrait($this, $this, $this);
        $this->setupPostCreateTrait($this, $this, $this);

        $lagoonClientProvider = $engine->getPolydockServiceProviderSingletonInstance('PolydockServiceProviderFTLagoon');

        // TODO: BMK this doesn't use the correct interfaces - we need to fix this globally.
        // The hack was to replace LagoonClientProviderInterface with PolydockServiceProviderInterface
        // This is not acceptable long term, or even beyond the week of the 15 of September 2025.
        // if (! $lagoonClientProvider instanceof PolydockServiceProviderInterface) {
        //     throw new PolydockAppInstanceStatusFlowException('Lagoon client provider is not an instance of PolydockServiceProviderInterface');
        // }
        $this->lagoonClientProvider = $lagoonClientProvider;
        /** @phpstan-ignore-next-line */
        $this->lagoonClient = $this->lagoonClientProvider->getLagoonClient();

        if (! ($this->lagoonClient instanceof LagoonClient)) {
            throw new PolydockAppInstanceStatusFlowException('Lagoon client is not an instance of LagoonClient');
        }
    }

    /**
     * @param  array<string, mixed>  $logContext
     */
    public function verifyLagoonValuesAreAvailable(PolydockAppInstanceInterface $appInstance, array $logContext = []): bool
    {
        $lagoonDeployGit = $appInstance->getKeyValue('lagoon-deploy-git');
        $lagoonRegionId = $appInstance->getKeyValue('lagoon-deploy-region-id');
        $lagoonPrivateKey = $appInstance->getKeyValue('lagoon-deploy-private-key');
        $lagoonOrganizationId = $appInstance->getKeyValue('lagoon-deploy-organization-id');
        $lagoonGroupName = $appInstance->getKeyValue('lagoon-deploy-group-name');
        $lagoonProjectPrefix = $appInstance->getKeyValue('lagoon-deploy-project-prefix');
        $lagoonProjectName = $appInstance->getKeyValue('lagoon-project-name');
        $lagoonAppInstanceHealthWebhookUrl = $appInstance->getKeyValue('polydock-app-instance-health-webhook-url');
        $appType = $appInstance->getAppType();

        if (! $lagoonDeployGit) {
            if ($this->lagoonClient && $this->lagoonClient->getDebug()) {
                $this->debug('Lagoon deploy git value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonRegionId) {
            if ($this->lagoonClient && $this->lagoonClient->getDebug()) {
                $this->debug('Lagoon region id value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonPrivateKey) {
            if ($this->lagoonClient && $this->lagoonClient->getDebug()) {
                $this->debug('Lagoon private key value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonOrganizationId) {
            if ($this->lagoonClient && $this->lagoonClient->getDebug()) {
                $this->debug('Lagoon organization id value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonGroupName) {
            if ($this->lagoonClient && $this->lagoonClient->getDebug()) {
                $this->debug('Lagoon group name value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonProjectPrefix) {
            if ($this->lagoonClient && $this->lagoonClient->getDebug()) {
                $this->debug('Lagoon project prefix value not set', $logContext);
            }

            return false;
        }

        if (! $appType) {
            if ($this->lagoonClient && $this->lagoonClient->getDebug()) {
                $this->debug('App type value not set, and Polydock needs this to be set in Lagoon', $logContext);
            }

            return false;
        }

        if (! $lagoonProjectName) {
            if ($this->lagoonClient && $this->lagoonClient->getDebug()) {
                $this->debug('Lagoon project name value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonAppInstanceHealthWebhookUrl) {
            if ($this->lagoonClient && $this->lagoonClient->getDebug()) {
                $this->debug('Lagoon app instance health webhook url value not set', $logContext);
            }

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $logContext
     */
    public function verifyLagoonProjectNameIsAvailable(PolydockAppInstanceInterface $appInstance, array $logContext = []): bool
    {
        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        if (! $projectName) {
            if ($this->lagoonClient && $this->lagoonClient->getDebug()) {
                $this->debug('Lagoon project name not available', $logContext);
            }

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $logContext
     */
    public function verifyLagoonProjectIdIsAvailable(PolydockAppInstanceInterface $appInstance, array $logContext = []): bool
    {
        $projectId = $appInstance->getKeyValue('lagoon-project-id');
        if (! $projectId) {
            if ($this->lagoonClient && $this->lagoonClient->getDebug()) {
                $this->debug('Lagoon project id not available', $logContext);
            }

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $logContext
     */
    public function verifyLagoonProject(PolydockAppInstanceInterface $appInstance, array $logContext = []): bool
    {
        if (! $this->verifyLagoonProjectNameIsAvailable($appInstance, $logContext)) {
            return false;
        }

        if (! $this->verifyLagoonProjectIdIsAvailable($appInstance, $logContext)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $logContext
     */
    public function validateLagoonPing(array $logContext = []): void
    {
        $ping = $this->pingLagoonAPI();
        if (! $ping) {
            $this->error('Lagoon API ping failed', $logContext);
            throw new PolydockAppInstanceStatusFlowException('Lagoon API ping failed');
        }
    }

    /**
     * @param  array<string, mixed>  $logContext
     *
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function validateAndSetupLagoon(
        PolydockAppInstanceInterface $appInstance,
        PolydockAppInstanceStatus $expectedStatus,
        array $logContext = [],
        bool $testLagoonPing = true,
        bool $verifyLagoonValuesAreAvailable = true,
        bool $verifyLagoonProjectNameIsAvailable = true,
        bool $verifyLagoonProjectIdIsAvailable = true
    ): void {
        $this->validateAppInstanceStatusIsExpected($appInstance, $expectedStatus);
        $this->setLagoonClientFromAppInstance($appInstance);

        if ($testLagoonPing) {
            $this->validateLagoonPing($logContext);
            $this->info('Lagoon API ping successful', $logContext);
        }

        if ($verifyLagoonValuesAreAvailable) {
            if (! $this->verifyLagoonValuesAreAvailable($appInstance, $logContext)) {
                $this->error('Required Lagoon values not available', $logContext);
                throw new PolydockAppInstanceStatusFlowException('Required Lagoon values not available');
            }
        }

        if ($verifyLagoonProjectNameIsAvailable) {
            if (! $this->verifyLagoonProjectNameIsAvailable($appInstance, $logContext)) {
                $this->error('Lagoon project name not available', $logContext);
                throw new PolydockAppInstanceStatusFlowException('Lagoon project name not available');
            }
        }

        if ($verifyLagoonProjectIdIsAvailable) {
            if (! $this->verifyLagoonProjectIdIsAvailable($appInstance, $logContext)) {
                $this->error('Lagoon project id not available', $logContext);
                throw new PolydockAppInstanceStatusFlowException('Lagoon project id not available');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getLogContext(string $location): array
    {
        return ['class' => self::class, 'location' => $location];
    }

    public function addOrUpdateLagoonProjectVariable(PolydockAppInstanceInterface $appInstance, string $variableName, string $variableValue, string $variableScope): void
    {
        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $projectId = $appInstance->getKeyValue('lagoon-project-id');
        $logContext = $this->getLogContext('addOrUpdateLagoonProjectVariable');
        $logContext['projectName'] = $projectName;
        $logContext['projectId'] = $projectId;
        $logContext['variableName'] = $variableName;
        $logContext['variableValue'] = $variableValue;
        $logContext['variableScope'] = $variableScope;

        if ($this->lagoonClient) {
            $variable = $this->lagoonClient->addOrUpdateScopedVariableForProject($projectName, $variableName, $variableValue, $variableScope);

            if (isset($variable['error'])) {
                $this->error("Failed to add or update {$variableName} variable",
                    $logContext + [
                        'lagoonVariable' => $variable,
                        'error' => $variable['error'],
                    ]);
                throw new \Exception("Failed to add or update {$variableName} variable");
            }

            if ($this->lagoonClient->getDebug()) {
                $this->debug('Added or updated variable', $logContext);
            }
        }
    }

    public function getRequiresAiInfrastructure(): bool
    {
        return $this->requiresAiInfrastructure;
    }

    public function setRequiresAiInfrastructure(bool $requiresAiInfrastructure): void
    {
        $this->requiresAiInfrastructure = $requiresAiInfrastructure;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    #[\Override]
    public function info(string $message, array $context = []): PolydockAppBase
    {
        // Delegate to parent class logging (inherited from PolydockAppBase)
        return parent::info($message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    #[\Override]
    public function error(string $message, array $context = []): PolydockAppBase
    {
        // Delegate to parent class logging (inherited from PolydockAppBase)
        return parent::error($message, $context);
    }

    /**
     ** NOTE: The following two methods _should_ have been pulled from an upstream class
     ** The next step there will be to refactor the upstream class to use these methods in a trait
     ** so that we can avoid code duplication.
     */
    /** @phpstan-ignore-next-line
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function validateLagoonPingAndThrowExceptionIfFailed($logContext = []): void
    {
        $ping = $this->pingLagoonAPI();
        if (! $ping) {
            $this->error('Lagoon API ping failed', $logContext);
            throw new PolydockAppInstanceStatusFlowException('Lagoon API ping failed');
        }
    }

    /** @phpstan-ignore-next-line */
    public function validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
        PolydockAppInstanceInterface $appInstance,
        PolydockAppInstanceStatus $expectedStatus,
        $logContext = [],
        bool $testLagoonPing = true,
        bool $verifyLagoonValuesAreAvailable = true,
        bool $verifyLagoonProjectNameIsAvailable = true,
        bool $verifyLagoonProjectIdIsAvailable = true
    ): void {
        // $this->validateAppInstanceStatusIsExpected($appInstance, $expectedStatus, $logContext);
        // $this->setLagoonClientFromAppInstance($appInstance, $logContext);
        $this->validateAppInstanceStatusIsExpected($appInstance, $expectedStatus);
        $this->setLagoonClientFromAppInstance($appInstance);

        if ($testLagoonPing) {
            $this->validateLagoonPingAndThrowExceptionIfFailed($appInstance);
            $this->info('Lagoon API ping successful', $logContext);
        }

        if ($verifyLagoonValuesAreAvailable) {
            if (! $this->verifyLagoonValuesAreAvailable($appInstance, $logContext)) {
                $this->error('Required Lagoon values not available', $logContext);
                throw new PolydockAppInstanceStatusFlowException('Required Lagoon values not available');
            }
        }

        if ($verifyLagoonProjectNameIsAvailable) {
            if (! $this->verifyLagoonProjectNameIsAvailable($appInstance, $logContext)) {
                $this->error('Lagoon project name not available', $logContext);
                throw new PolydockAppInstanceStatusFlowException('Lagoon project name not available');
            }
        }

        if ($verifyLagoonProjectIdIsAvailable) {
            if (! $this->verifyLagoonProjectIdIsAvailable($appInstance, $logContext)) {
                $this->error('Lagoon project id not available', $logContext);
                throw new PolydockAppInstanceStatusFlowException('Lagoon project id not available');
            }
        }
    }
}
