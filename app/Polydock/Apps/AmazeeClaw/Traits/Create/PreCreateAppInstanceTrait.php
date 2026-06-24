<?php

declare(strict_types=1);

namespace App\Polydock\Apps\AmazeeClaw\Traits\Create;

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

        // Call the hook to extract AI credentials from the initial request data if in manual mode
        if ($this->resolveAmazeeAiKeyMode($appInstance) === 'manual') {
            if (method_exists($this, 'extractAiCredentialsFromHookData')) {
                $this->extractAiCredentialsFromHookData($appInstance, $appInstance->config['request_data'] ?? []);
            }
        }

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $projectPrefix = $appInstance->getKeyValue('lagoon-deploy-project-prefix');

        $this->info("{$functionName}: Initial project name check", $logContext + ['projectName' => $projectName, 'projectPrefix' => $projectPrefix]);

        // If a prefix is set, we always want to ensure the name follows the prefix-driven pattern
        // BUT we should try to incorporate the requested name if it exists.
        if ($projectPrefix !== '') {
            $baseName = $projectName ?: $projectPrefix;
            // Ensure baseName doesn't already start with prefix if we're prepending it
            if ($projectName && ! str_starts_with($projectName, $projectPrefix)) {
                $baseName = "{$projectPrefix}-{$projectName}";
            }

            // Sanitize base name: lowercase, alphanumeric and hyphens only
            $baseName = strtolower((string) preg_replace('/[^a-z0-9-]+/', '-', $baseName));
            $baseName = trim($baseName, '-');

            // Check if this project name already exists on Lagoon
            $finalProjectName = $baseName;
            $attempts = 0;
            $maxAttempts = 10;

            while ($this->lagoonClient->projectExistsByName($finalProjectName)) {
                if ($attempts >= $maxAttempts) {
                    $this->error("{$functionName}: Failed to generate a unique project name after {$maxAttempts} attempts", $logContext);
                    throw new \Exception('Failed to generate a unique project name for Lagoon');
                }
                $this->info("{$functionName}: Project name {$finalProjectName} already exists on Lagoon, generating unique variant", $logContext);
                $finalProjectName = $this->generateUniqueProjectName($baseName);
                $attempts++;
            }

            $projectName = $finalProjectName;
            $this->info("{$functionName}: Final unique project name: {$projectName}", $logContext);

            /** @phpstan-ignore-next-line */
            $appInstance->setName($projectName);
            $appInstance->storeKeyValue('lagoon-project-name', $projectName);
            $appInstance->save();
        } elseif ($projectName === '') {
            // No name and no prefix? This should probably not happen if validation passed, but let's be safe.
            $projectName = $this->generateUniqueProjectName('polydock');
            /** @phpstan-ignore-next-line */
            $appInstance->setName($projectName);
            $appInstance->storeKeyValue('lagoon-project-name', $projectName);
            $appInstance->save();
        }

        $this->info("{$functionName}: starting for project: {$projectName}", $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING,
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING->getStatusMessage()
        )->save();

        $this->info("{$functionName}: completed", $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::PRE_CREATE_COMPLETED, 'Pre-create completed')->save();

        return $appInstance;
    }

    /**
     * Local override point for project-name strategy.
     *
     * Tweak this method directly if you want a different order/length,
     * e.g. animal before color, or a shorter unique id.
     */
    protected function generateUniqueProjectName(string $prefix): string
    {
        $uniqueIdLengthBytes = 3; // 6 hex chars
        try {
            $shortUniqueId = bin2hex(random_bytes($uniqueIdLengthBytes));
        } catch (\Exception) {
            // Fallback preserves randomness if secure source is unavailable.
            $shortUniqueId = substr(hash('sha256', uniqid('', true)), 0, $uniqueIdLengthBytes * 2);
        }

        return strtolower(
            "{$prefix}-{$this->pickAdjective()}-{$this->pickAnimal()}-{$shortUniqueId}"
        );
    }

    protected function pickAnimal(): string
    {
        $animals = [
            'crab', 'lobster', 'crayfish', 'prawn', 'shrimp',
            'hermitcrab', 'fiddlercrab', 'kingcrab', 'rocklobster', 'langoustine',
            'scorpion', 'mantis',
        ];

        return $animals[array_rand($animals)];
    }

    protected function pickAdjective(): string
    {
        $adjectives = [
            'snappy', 'pinchy', 'crabby', 'clawesome', 'nippy',
            'cheeky', 'zesty', 'scrappy', 'wiggly', 'spiky',
            'grumpy', 'sassy', 'bouncy', 'sneaky', 'jolly',
        ];

        return $adjectives[array_rand($adjectives)];
    }
}
