<?php

namespace App\Polydock\Apps\Generic\Traits\Deploy;

use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInstanceStatusFlowException;

trait PostDeployAppInstanceTrait
{
    /**
     * Handles post-deployment tasks for an app instance.
     *
     * This method is called after deploying the app instance. It validates the instance
     * is in the correct status, sets it to running, executes post-deployment logic,
     * and marks it as completed.
     *
     * @param  PolydockAppInstanceInterface  $appInstance  The app instance to process
     * @return PolydockAppInstanceInterface The processed app instance
     *
     * @throws PolydockAppInstanceStatusFlowException If instance is not in PENDING_POST_DEPLOY status
     */
    public function postDeployAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
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
            PolydockAppInstanceStatus::PENDING_POST_DEPLOY,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $deployEnvironment = $appInstance->getKeyValue('lagoon-deploy-branch');
        $logContext = $logContext + ['projectName' => $projectName, 'deployEnvironment' => $deployEnvironment];

        $this->info($functionName.': starting for project: '.$projectName, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::POST_DEPLOY_RUNNING,
            PolydockAppInstanceStatus::POST_DEPLOY_RUNNING->getStatusMessage()
        )->save();

        $postDeployScript = $appInstance->getKeyValue('lagoon-post-deploy-script');
        $postDeployScriptService = $appInstance->getKeyValue('lagoon-post-deploy-script-service') ?? 'cli';
        $postDeployScriptContainer = $appInstance->getKeyValue('lagoon-post-deploy-script-container') ?? 'cli';
        $logContext = $logContext + ['postDeployScript' => $postDeployScript, 'postDeployScriptService' => $postDeployScriptService, 'postDeployScriptContainer' => $postDeployScriptContainer];

        if (! empty($postDeployScript)) {
            $this->info('Post-deploy script', $logContext);

            try {
                $trialResult = $this->lagoonClient->executeCommandOnProjectEnvironment(
                    $projectName,
                    $deployEnvironment,
                    $postDeployScript,
                    $postDeployScriptService,
                    $postDeployScriptContainer
                );

                $this->info('Trial result', $logContext + ['trialResult' => $trialResult]);

                if ($trialResult['result'] !== 0) {
                    throw new \Exception($trialResult['result'].' | '.$trialResult['result_text'].' | '.$trialResult['error']);
                }

            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $appInstance->setStatus(PolydockAppInstanceStatus::POST_DEPLOY_FAILED, substr($e->getMessage(), 0, 100))->save();

                return $appInstance;
            }
        } else {
            $this->info('No post-deploy script detected', $logContext);
        }

        //        $this->addOrUpdateLagoonProjectVariable($appInstance, "POLYDOCK_APP_LAST_DEPLOYED_DATE", date('Y-m-d'), "GLOBAL");
        //        $this->addOrUpdateLagoonProjectVariable($appInstance, "POLYDOCK_APP_LAST_DEPLOYED_TIME", date('H:i:s'), "GLOBAL");

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED, 'Post-deploy completed')->save();

        return $appInstance;
    }
}
