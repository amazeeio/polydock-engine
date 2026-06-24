<?php

namespace App\Polydock\Apps\PrivateGpt\Traits\Create;

use App\Polydock\Apps\PrivateGpt\Interfaces\AmazeeAiOperationsInterface;
use App\Polydock\Apps\PrivateGpt\Interfaces\LagoonOperationsInterface;
use App\Polydock\Apps\PrivateGpt\Interfaces\LoggerInterface;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;

trait PreCreateAppInstanceTrait
{
    protected ?LoggerInterface $preCreateLogger = null;

    protected ?LagoonOperationsInterface $preCreateLagoonOps = null;

    protected ?AmazeeAiOperationsInterface $preCreateAmazeeAiOps = null;

    /**
     * Setup trait dependencies
     */
    public function setupPreCreateTrait(
        ?LoggerInterface $logger = null,
        ?LagoonOperationsInterface $lagoonOps = null,
        ?AmazeeAiOperationsInterface $amazeeAiOps = null
    ): void {
        $this->preCreateLogger = $logger;
        $this->preCreateLagoonOps = $lagoonOps;
        $this->preCreateAmazeeAiOps = $amazeeAiOps;
    }

    /**
     * Ensure trait is initialized with dependencies
     */
    private function ensurePreCreateTraitInitialized(): void
    {
        if ($this->preCreateLogger === null && $this instanceof LoggerInterface) {
            $this->preCreateLogger = $this;
        }
        if ($this->preCreateLagoonOps === null && $this instanceof LagoonOperationsInterface) {
            $this->preCreateLagoonOps = $this;
        }
        if ($this->preCreateAmazeeAiOps === null && $this instanceof AmazeeAiOperationsInterface) {
            $this->preCreateAmazeeAiOps = $this;
        }
    }

    public function preCreateAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $this->ensurePreCreateTraitInitialized();

        $functionName = __FUNCTION__;
        $logContext = $this->preCreateLogger?->getLogContext($functionName) ?? [];
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = false;

        $this->preCreateLogger?->info($functionName.': starting', $logContext);

        $this->preCreateLagoonOps?->validateAndSetupLagoon(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_PRE_CREATE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $this->preCreateAmazeeAiOps?->setAmazeeAiClientFromAppInstance($appInstance);

        $projectName = $appInstance->getKeyValue('lagoon-project-name');

        $companyName = $appInstance->getKeyValue('company-name');
        if ($companyName != '') {
            /** @phpstan-ignore-next-line */
            $appInstance->setName($this->addUniquePostfixToString($companyName)); // Force a name change to avoid unique constraint issues
            $projectName = $appInstance->getName();
            $appInstance->storeKeyValue('lagoon-project-name', $projectName); // ensure this is set, even if it was not provided
            $appInstance->save();
        } // else we stick with the standard

        $this->preCreateLogger?->info($functionName.': starting for project: '.$projectName, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING,
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING->getStatusMessage()
        )->save();

        $team = $this->preCreateAmazeeAiOps?->createTeamAndSetupAdministrator($appInstance);

        if ($team) {
            $appInstance->storeKeyValue('amazee-ai-team-id', (string) $team->id);
            $appInstance->storeKeyValue('amazee-ai-team-name', $team->name);
        }

        // Here we want to override the app admin username to the incoming registration email
        // TODO: this is quite nasty, and should be supported by polydock core
        $adminEmail = $appInstance->getKeyValue('user-email');
        $appInstance->storeKeyValue('lagoon-generate-app-admin-username', $adminEmail); // since this is saved below, let's see if this works.

        // We don't want to send email details in the mail
        $appInstance->storeKeyValue('hide-login-email-info', 'true');

        $this->preCreateLogger?->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::PRE_CREATE_COMPLETED, 'Pre-create completed')->save();

        return $appInstance;
    }

    protected function onlyAlphanumericStartsChar(string $input): string
    {
        // Remove non-alphanumeric characters from the start
        return preg_replace('/[^a-z0-9]+/', '', strtolower($input)) ?? '';
    }

    protected function addUniquePostfixToString(string $baseString): string
    {
        $uniquePostfix = substr($this->onlyAlphanumericStartsChar(base64_encode(random_bytes(6))), 0, 8);

        return strtolower($this->onlyAlphanumericStartsChar($baseString).'-'.$uniquePostfix);
    }
}
