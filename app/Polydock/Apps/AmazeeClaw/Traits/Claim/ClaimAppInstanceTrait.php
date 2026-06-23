<?php

declare(strict_types=1);

namespace App\Polydock\Apps\AmazeeClaw\Traits\Claim;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;

trait ClaimAppInstanceTrait
{
    public function claimAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
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
            PolydockAppInstanceStatus::PENDING_POLYDOCK_CLAIM,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $deployEnvironment = $appInstance->getKeyValue('lagoon-deploy-branch');
        $logContext += [
            'projectName' => $projectName,
            'deployEnvironment' => $deployEnvironment,
        ];

        $this->info("{$functionName}: starting claim of project: {$projectName}", $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::POLYDOCK_CLAIM_RUNNING,
            PolydockAppInstanceStatus::POLYDOCK_CLAIM_RUNNING->getStatusMessage()
        )->save();

        $claimScript = $appInstance->getKeyValue('lagoon-claim-script');
        $claimScriptService = $appInstance->getKeyValue('lagoon-claim-script-service') ?? 'openclaw-gateway';
        $claimScriptContainer = $appInstance->getKeyValue('lagoon-claim-script-container') ?? 'node';

        $logContext += [
            'claimScript' => $claimScript,
            'claimScriptService' => $claimScriptService,
            'claimScriptContainer' => $claimScriptContainer,
        ];

        try {
            // Keep existing claim marker behavior.
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_CLAIMED_AT', date('Y-m-d H:i:s'), 'GLOBAL');

            // Provision/reuse credentials for the assigned user/team and inject into Lagoon.
            $claimEnvironmentVariables = $this->provisionAndInjectManualAmazeeAiCredentials($appInstance, $logContext);

            if (! empty($claimScript)) {
                $this->info('Claim script', $logContext);
                $claimScriptWithEnvironment = $this->buildClaimScriptWithInlineEnvironmentVariables($claimScript, $claimEnvironmentVariables);

                $claimResult = $this->lagoonClient->executeCommandOnProjectEnvironment(
                    $projectName,
                    $deployEnvironment,
                    $claimScriptWithEnvironment,
                    $claimScriptService,
                    $claimScriptContainer
                );

                $this->info('Claim result', $logContext + ['claimResult' => $claimResult]);

                if (($claimResult['result'] ?? 1) !== 0) {
                    throw new \Exception(
                        ($claimResult['result'] ?? '')
                        .' | '.($claimResult['result_text'] ?? '')
                        .' | '.($claimResult['error'] ?? '')
                    );
                }

                if (! isset($claimResult['output'])) {
                    throw new \Exception(
                        'No output from claim command: '
                        .($claimResult['result'] ?? '')
                        .' | '.($claimResult['result_text'] ?? '')
                        .' | '.($claimResult['error'] ?? '')
                    );
                }

                if (! filter_var(trim((string) $claimResult['output']), FILTER_VALIDATE_URL)) {
                    throw new \Exception('Claim command output is not a valid URL: '.$claimResult['output']);
                }

                $appInstance->storeKeyValue('claim-command-output', trim((string) $claimResult['output']));
                $appInstance->setAppUrl((string) $claimResult['output'], (string) $claimResult['output'], 24);
            } else {
                $this->info('No claim script detected', $logContext);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage(), $logContext + [
                'exception_class' => \get_class($e),
            ]);
            $appInstance->setStatus(PolydockAppInstanceStatus::POLYDOCK_CLAIM_FAILED, substr($e->getMessage(), 0, 100))->save();

            return $appInstance;
        }

        $this->info("{$functionName}: completed", $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::POLYDOCK_CLAIM_COMPLETED, 'Claim completed')->save();

        return $appInstance;
    }
}
