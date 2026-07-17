<?php

namespace App\Polydock\Apps\Generic;

use App\Polydock\Apps\Generic\Traits\Claim\ClaimAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Create\CreateAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Create\PostCreateAppInstanceTrait;
use App\Polydock\Apps\Generic\Traits\Create\PreCreateAppInstanceTrait;
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
use App\Polydock\Clients\Lagoon\Client as LagoonClient;
use App\Polydock\Clients\Lagoon\LagoonClientInitializeRequiredToInteractException;
use App\Polydock\Core\Attributes\PolydockAppTitle;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppBase;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;
use App\Polydock\Core\PolydockAppVariableDefinitionBase;
use App\Polydock\Core\PolydockAppVariableDefinitionInterface;
use App\Polydock\Core\PolydockEngineInterface;
use App\Polydock\Core\PolydockServiceProviderInterface;
use App\PolydockServiceProviders\PolydockServiceProviderFTLagoon;

#[PolydockAppTitle('Generic Lagoon App')]
class PolydockApp extends PolydockAppBase
{
    use ClaimAppInstanceTrait;
    use CreateAppInstanceTrait;
    use DeployAppInstanceTrait;
    use PollDeployProgressAppInstanceTrait;
    use PollHealthProgressAppInstanceTrait;
    use PollUpgradeProgressAppInstanceTrait;
    use PostCreateAppInstanceTrait;
    use PostDeployAppInstanceTrait;
    use PostRemoveAppInstanceTrait;
    use PostUpgradeAppInstanceTrait;
    use PreCreateAppInstanceTrait;
    use PreDeployAppInstanceTrait;
    use PreRemoveAppInstanceTrait;
    use PreUpgradeAppInstanceTrait;
    use RemoveAppInstanceTrait;
    use UpgradeAppInstanceTrait;

    protected bool $requiresAiInfrastructure = false;

    public static string $version = '0.0.2';

    protected LagoonClient $lagoonClient;

    protected PolydockEngineInterface $engine;

    protected PolydockServiceProviderInterface $lagoonClientProvider;

    /**
     * Get the default variable definitions for this app specifically
     *
     * @return array<PolydockAppVariableDefinitionInterface>
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
            new PolydockAppVariableDefinitionBase('lagoon-auto-idle'),
            new PolydockAppVariableDefinitionBase('lagoon-production-environment'),
        ];
    }

    /**
     * Get the version of the app
     */
    public static function getAppVersion(): string
    {
        return self::$version;
    }

    /**
     * Pings the Lagoon API to check if it is running
     *
     * @throws PolydockAppInstanceStatusFlowException If lagoon client is not found
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

    /**
     * Sets the lagoon client from the app instance.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to set the lagoon client from
     *
     * @throws PolydockAppInstanceStatusFlowException If lagoon client is not found
     */
    public function setLagoonClientFromAppInstance(PolydockAppInstanceInterface $appInstance): void
    {
        $engine = $appInstance->getEngine();
        $this->engine = $engine;

        /**
         * @var PolydockServiceProviderFTLagoon
         */
        $lagoonClientProvider = $engine->getPolydockServiceProviderSingletonInstance('PolydockServiceProviderFTLagoon');
        $this->lagoonClientProvider = $lagoonClientProvider;

        if (! method_exists($lagoonClientProvider, 'getLagoonClient')) {
            throw new PolydockAppInstanceStatusFlowException('Lagoon client provider does not have getLagoonClient method');
        }

        $this->lagoonClient = $lagoonClientProvider->getLagoonClient();

        if (! $this->lagoonClient) {
            throw new PolydockAppInstanceStatusFlowException('Lagoon client not found');
        }

        if (! ($this->lagoonClient instanceof LagoonClient)) {
            throw new PolydockAppInstanceStatusFlowException('Lagoon client is not an instance of LagoonClient');
        }
    }

    /**
     * Grant the instance's deploy group access to its Lagoon project.
     *
     * @throws \Exception If Lagoon rejects the grant or returns no id
     */
    public function addDeployGroupToLagoonProject(PolydockAppInstanceInterface $appInstance): void
    {
        $result = $this->lagoonClient->addGroupToProject(
            $appInstance->getKeyValue('lagoon-deploy-group-name'),
            $appInstance->getKeyValue('lagoon-project-name')
        );

        if (isset($result['error'])) {
            // Handle both array errors (from GraphQL) and string errors (from not found)
            $errorMessage = is_array($result['error'])
                ? ($result['error'][0]['message'] ?? json_encode($result['error']))
                : $result['error'];
            $this->error($errorMessage);
            throw new \Exception($errorMessage);
        }

        if (! isset($result['addGroupsToProject']['id'])) {
            $this->error('addGroupsToProject ID not found in data');
            throw new \Exception('addGroupsToProject ID not found in data');
        }
    }

    /**
     * Verifies that the lagoon values are available.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to verify
     * @return bool True if the lagoon values are available, false otherwise
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
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon deploy git value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonRegionId) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon region id value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonPrivateKey) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon private key value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonOrganizationId) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon organization id value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonGroupName) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon group name value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonProjectPrefix) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon project prefix value not set', $logContext);
            }

            return false;
        }

        if (! $appType) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('App type value not set, and Polydock needs this to be set in Lagoon', $logContext);
            }

            return false;
        }

        if (! $lagoonProjectName) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon project name value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonAppInstanceHealthWebhookUrl) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon app instance health webhook url value not set', $logContext);
            }

            return false;
        }

        return true;
    }

    /**
     * Verifies that the project name is available.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to verify
     * @return bool True if the project name is available, false otherwise
     */
    public function verifyLagoonProjectNameIsAvailable(PolydockAppInstanceInterface $appInstance, array $logContext = []): bool
    {
        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        if (! $projectName) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon project name not available', $logContext);
            }

            return false;
        }

        return true;
    }

    /**
     * Verifies that the project id is available.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to verify
     * @return bool True if the project id is available, false otherwise
     */
    public function verifyLagoonProjectIdIsAvailable(PolydockAppInstanceInterface $appInstance, array $logContext = []): bool
    {
        $projectId = $appInstance->getKeyValue('lagoon-project-id');
        if (! $projectId) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon project id not available', $logContext);
            }

            return false;
        }

        return true;
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function validateLagoonPingAndThrowExceptionIfFailed(array $logContext = []): void
    {
        $ping = $this->pingLagoonAPI();
        if (! $ping) {
            $this->error('Lagoon API ping failed', $logContext);
            throw new PolydockAppInstanceStatusFlowException('Lagoon API ping failed');
        }
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
        PolydockAppInstanceInterface $appInstance,
        PolydockAppInstanceStatus $expectedStatus,
        $logContext = [],
        bool $testLagoonPing = true,
        bool $verifyLagoonValuesAreAvailable = true,
        bool $verifyLagoonProjectNameIsAvailable = true,
        bool $verifyLagoonProjectIdIsAvailable = true
    ): void {
        $this->validateAppInstanceStatusIsExpected($appInstance, $expectedStatus);
        $this->setLagoonClientFromAppInstance($appInstance);

        if ($testLagoonPing) {
            $this->validateLagoonPingAndThrowExceptionIfFailed($logContext);
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
     * Shared skeleton of every lifecycle stage: validate the expected status
     * (configuring the Lagoon client), mark *_RUNNING, run the stage body,
     * and mark *_COMPLETED — mapping thrown exceptions to *_FAILED. The body
     * may itself set a failure status and return non-null to short-circuit.
     *
     * @param  callable(PolydockAppInstanceInterface, array): ?PolydockAppInstanceInterface  $body
     *                                                                                              Receives ($appInstance, $logContext). Return the instance to
     *                                                                                              short-circuit (body already set a terminal status), or null to
     *                                                                                              let the template mark the stage completed.
     */
    protected function runLifecyclePhase(
        PolydockAppInstanceInterface $appInstance,
        string $functionName,
        PolydockAppInstanceStatus $expectedStatus,
        PolydockAppInstanceStatus $runningStatus,
        PolydockAppInstanceStatus $completedStatus,
        PolydockAppInstanceStatus $failedStatus,
        callable $body,
        string $completedMessage,
        bool $testLagoonPing = true,
        bool $validateLagoonValues = true,
        bool $validateLagoonProjectName = true,
        bool $validateLagoonProjectId = true,
    ): PolydockAppInstanceInterface {
        $logContext = $this->getLogContext($functionName);
        $this->info($functionName.': starting', $logContext);

        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance, $expectedStatus, $logContext,
            $testLagoonPing, $validateLagoonValues,
            $validateLagoonProjectName, $validateLagoonProjectId
        );

        $appInstance->setStatus($runningStatus, $runningStatus->getStatusMessage())->save();

        try {
            $shortCircuit = $body($appInstance, $logContext);
            if ($shortCircuit !== null) {
                return $shortCircuit;
            }
        } catch (\Exception $e) {
            $this->error($functionName.' failed: '.$e->getMessage(), $logContext + [
                'exception_class' => get_class($e),
            ]);
            $appInstance->setStatus($failedStatus, 'An exception occurred: '.$e->getMessage())->save();

            return $appInstance;
        }

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus($completedStatus, $completedMessage)->save();

        return $appInstance;
    }

    /**
     * Get the log context for a specific function.
     *
     * @param  string  $location  The location of the log context
     * @return array The log context
     */
    public function getLogContext(string $location): array
    {
        return ['class' => self::class, 'location' => $location];
    }

    /**
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function addOrUpdateLagoonProjectVariable(PolydockAppInstanceInterface $appInstance, $variableName, $variableValue, $variableScope): void
    {
        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $projectId = $appInstance->getKeyValue('lagoon-project-id');
        $logContext = $this->getLogContext('addOrUpdateLagoonProjectVariable');
        $logContext['projectName'] = $projectName;
        $logContext['projectId'] = $projectId;
        $logContext['variableName'] = $variableName;
        $logContext['variableValue'] = '[REDACTED]';
        $logContext['variableScope'] = $variableScope;

        $variable = $this->lagoonClient->addOrUpdateScopedVariableForProject($projectName, $variableName, $variableValue, $variableScope);

        if (isset($variable['error'])) {
            $errorMessage = \is_array($variable['error'])
                ? ($variable['error'][0]['message'] ?? json_encode($variable['error']))
                : (string) $variable['error'];

            $this->error("Failed to add or update {$variableName} variable",
                $logContext + [
                    'lagoonVariable' => $variable,
                    'error' => $variable['error'],
                    'parsed_error' => $errorMessage,
                ]);
            throw new \Exception("Failed to add or update {$variableName} variable: ".$errorMessage);
        }

        if ($this->lagoonClient->getDebug()) {
            $this->debug('Added or updated variable', $logContext);
        }
    }

    public function getRequiresAiInfrastructure(): bool
    {
        return $this->requiresAiInfrastructure;
    }
}
