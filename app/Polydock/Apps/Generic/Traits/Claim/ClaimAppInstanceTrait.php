<?php

namespace App\Polydock\Apps\Generic\Traits\Claim;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

trait ClaimAppInstanceTrait
{
    /**
     * Handles upgrade tasks for an app instance.
     *
     * This method is to upgrade the app instance. It validates the instance
     * is in the correct status, sets it to running, executes upgrade logic,
     * and marks it as completed.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to process
     * @return PolydockAppInstanceInterface The processed app instance
     *
     * @throws PolydockAppInstanceStatusFlowException If instance is not in PENDING_UPGRADE status
     */
    public function claimAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;
        $logContext = $this->getLogContext($functionName);
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = true;

        $this->info($functionName.': starting', $logContext);

        // Throws PolydockAppInstanceStatusFlowException
        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $deployEnvironment = $appInstance->getKeyValue('lagoon-deploy-branch');
        $logContext = $logContext + ['projectName' => $projectName, 'deployEnvironment' => $deployEnvironment];

        $this->info($functionName.': starting claim of project: '.$projectName, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::POLYDOCK_CLAIM_RUNNING,
            PolydockAppInstanceStatus::POLYDOCK_CLAIM_RUNNING->getStatusMessage()
        )->save();

        $claimScript = $appInstance->getKeyValue('lagoon-claim-script');

        $claimScriptService = $appInstance->getKeyValue('lagoon-claim-script-service') ?? 'cli';
        $claimScriptContainer = $appInstance->getKeyValue('lagoon-claim-script-container') ?? 'cli';

        $logContext = $logContext + ['claimScript' => $claimScript, 'claimScriptService' => $claimScriptService, 'claimScriptContainer' => $claimScriptContainer];

        if (! empty($claimScript)) {
            $this->info('Claim script', $logContext);

            try {
                $claimResult = $this->lagoonClient->executeCommandOnProjectEnvironment(
                    $projectName,
                    $deployEnvironment,
                    $claimScript,
                    $claimScriptService,
                    $claimScriptContainer
                );

                $this->info('Claim result', $logContext + ['claimResult' => $claimResult]);

                if ($claimResult['result'] !== 0) {
                    throw new \Exception($claimResult['result'].' | '.$claimResult['result_text'].' | '.$claimResult['error']);
                }

                if (! isset($claimResult['output'])) {
                    throw new \Exception('No output from claim command: '.$claimResult['result'].' | '.$claimResult['result_text'].' | '.$claimResult['error']);
                }

                if (! filter_var(trim($claimResult['output']), FILTER_VALIDATE_URL)) {
                    throw new \Exception('Claim command output is not a valid URL: '.$claimResult['output']);
                }

                $appInstance->storeKeyValue('claim-command-output', trim($claimResult['output']));
                $appInstance->setAppUrl($claimResult['output'], $claimResult['output'], 24);

            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $appInstance->setStatus(PolydockAppInstanceStatus::POLYDOCK_CLAIM_FAILED, substr($e->getMessage(), 0, 100))->save();

                return $appInstance;
            }
        } else {
            $this->info('No claim script detected', $logContext);

            try {
                $routeUrl = $this->resolveClaimUrlFromLagoonRoutes($projectName, $deployEnvironment, $logContext);

                if (! empty($routeUrl)) {
                    $appInstance->storeKeyValue('claim-command-output', $routeUrl);
                    $appInstance->setAppUrl($routeUrl, $routeUrl, 24);
                    $this->info('Claim URL derived from Lagoon routes', $logContext + ['routeUrl' => $routeUrl]);
                } else {
                    $this->warning('No usable Lagoon route found for claim fallback', $logContext);
                }
            } catch (\Exception $e) {
                $this->error('Failed deriving claim URL from Lagoon routes: '.$e->getMessage(), $logContext);
            }
        }

        // We run this either way to ensure the variable is set
        try {
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_CLAIMED_AT', date('Y-m-d H:i:s'), 'GLOBAL');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $appInstance->setStatus(PolydockAppInstanceStatus::POLYDOCK_CLAIM_FAILED, substr($e->getMessage(), 0, 100))->save();

            return $appInstance;
        }

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::POLYDOCK_CLAIM_COMPLETED, 'Claim completed')->save();

        return $appInstance;
    }

    private function resolveClaimUrlFromLagoonRoutes(string $projectName, string $deployEnvironment, array $logContext = []): ?string
    {
        if (empty($projectName) || empty($deployEnvironment)) {
            return null;
        }

        $environment = $this->lagoonClient->getProjectEnvironmentByName($projectName, $deployEnvironment);
        if ($environment === []) {
            $this->warning('Lagoon environment not found for claim fallback', $logContext + [
                'projectName' => $projectName,
                'deployEnvironment' => $deployEnvironment,
            ]);

            return null;
        }

        $candidateRoutes = [];

        $primaryRoute = trim((string) ($environment['route'] ?? ''));
        if ($primaryRoute !== '') {
            $candidateRoutes[] = $primaryRoute;
        }

        $routes = explode(',', (string) ($environment['routes'] ?? ''));
        foreach ($routes as $route) {
            $route = trim($route);
            if ($route !== '') {
                $candidateRoutes[] = $route;
            }
        }

        $normalizedRoutes = [];
        foreach ($candidateRoutes as $route) {
            $normalizedRoutes[] = preg_match('/^https?:\/\//i', $route) === 1 ? $route : 'https://'.$route;
        }

        $normalizedRoutes = array_values(array_unique(array_filter($normalizedRoutes, static fn (string $route): bool => filter_var($route, FILTER_VALIDATE_URL) !== false
        )));

        if ($normalizedRoutes === []) {
            return null;
        }

        foreach ($normalizedRoutes as $route) {
            if (str_contains($route, 'frontend.')) {
                return $route;
            }
        }

        return $normalizedRoutes[0];
    }
}
