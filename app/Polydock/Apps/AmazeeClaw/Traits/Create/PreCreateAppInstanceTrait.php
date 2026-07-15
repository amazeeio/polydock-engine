<?php

declare(strict_types=1);

namespace App\Polydock\Apps\AmazeeClaw\Traits\Create;

use App\Polydock\Apps\AmazeeClaw\Enums\AmazeeAiKeyMode;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;

trait PreCreateAppInstanceTrait
{
    public function preCreateAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;
        $logContext = $this->getLogContext($functionName);
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = false;

        $this->info("{$functionName}: starting", $logContext);

        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_PRE_CREATE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        // Call the hook to extract externally-injected AI credentials from the
        // initial request data when keys are injected rather than generated.
        if ($this->resolveAmazeeAiKeyMode($appInstance) === AmazeeAiKeyMode::Injected) {
            if (method_exists($this, 'extractAiCredentialsFromHookData')) {
                $this->extractAiCredentialsFromHookData($appInstance, $appInstance->config['request_data'] ?? []);
            }
        }

        $this->info("{$functionName}: Initial project name check", $logContext + [
            'projectName' => $appInstance->getKeyValue('lagoon-project-name'),
            'projectPrefix' => $appInstance->getKeyValue('lagoon-deploy-project-prefix'),
        ]);

        // Store apps configured for custom naming accept externally supplied
        // names - prefix, sanitize, and dedupe on Lagoon. Pattern-named
        // (pre-warmed) instances are already unique and skip the Lagoon check.
        $this->finalizeCustomProjectNameIfConfigured($appInstance);

        $projectName = $appInstance->getKeyValue('lagoon-project-name');

        $this->info("{$functionName}: starting for project: {$projectName}", $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING,
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING->getStatusMessage()
        )->save();

        $this->info("{$functionName}: completed", $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::PRE_CREATE_COMPLETED, 'Pre-create completed')->save();

        return $appInstance;
    }
}
