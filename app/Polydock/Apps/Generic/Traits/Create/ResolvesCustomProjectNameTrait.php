<?php

namespace App\Polydock\Apps\Generic\Traits\Create;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStoreApp;
use App\Polydock\Core\PolydockAppInstanceInterface;

/**
 * Shared project-name finalization for apps whose instances can carry an
 * externally supplied (custom) name - e.g. from remote registration data.
 *
 * Ensures the name carries the store's project prefix, is sanitized for
 * Lagoon, and does not collide with an existing Lagoon project. Collision
 * variants use the store app's configured word lists when present.
 */
trait ResolvesCustomProjectNameTrait
{
    /**
     * Run finalization only for store apps configured for custom naming.
     */
    protected function finalizeCustomProjectNameIfConfigured(PolydockAppInstanceInterface $appInstance): void
    {
        $storeApp = $appInstance instanceof PolydockAppInstance ? $appInstance->storeApp : null;

        if ($storeApp?->project_naming_mode === PolydockStoreApp::PROJECT_NAMING_MODE_CUSTOM) {
            $this->finalizeCustomProjectName($appInstance);
        }
    }

    protected function finalizeCustomProjectName(PolydockAppInstanceInterface $appInstance): void
    {
        $projectName = (string) $appInstance->getKeyValue('lagoon-project-name');
        $projectPrefix = (string) $appInstance->getKeyValue('lagoon-deploy-project-prefix');

        if ($projectPrefix === '' && $projectName === '') {
            // No name and no prefix? This should probably not happen if validation passed, but let's be safe.
            $this->setProjectName($appInstance, $this->generateProjectNameVariant('polydock', $appInstance));

            return;
        }

        // Ensure the name follows the prefix-driven pattern while
        // incorporating the requested name if one exists.
        $baseName = $projectName !== '' ? $projectName : $projectPrefix;
        if ($projectPrefix !== '' && $projectName !== '' && ! str_starts_with($projectName, $projectPrefix)) {
            $baseName = "{$projectPrefix}-{$projectName}";
        }

        // Sanitize base name: lowercase, alphanumeric and hyphens only
        $baseName = strtolower((string) preg_replace('/[^a-z0-9-]+/i', '-', $baseName));
        $baseName = trim($baseName, '-');

        $finalProjectName = $baseName;
        $attempts = 0;
        $maxAttempts = 10;

        while ($this->lagoonClient->projectExistsByName($finalProjectName)) {
            if ($attempts >= $maxAttempts) {
                $this->error("finalizeCustomProjectName: Failed to generate a unique project name after {$maxAttempts} attempts");
                throw new \Exception('Failed to generate a unique project name for Lagoon');
            }
            $this->info("finalizeCustomProjectName: Project name {$finalProjectName} already exists on Lagoon, generating unique variant");
            $finalProjectName = $this->generateProjectNameVariant($baseName, $appInstance);
            $attempts++;
        }

        $this->setProjectName($appInstance, $finalProjectName);
    }

    protected function generateProjectNameVariant(string $prefix, PolydockAppInstanceInterface $appInstance): string
    {
        $storeApp = $appInstance instanceof PolydockAppInstance ? $appInstance->storeApp : null;

        $adjectives = $storeApp?->project_naming_adjectives ?: static::defaultProjectNamingAdjectives();
        $nouns = $storeApp?->project_naming_nouns ?: static::defaultProjectNamingNouns();

        $adjective = $adjectives === [] ? PolydockAppInstance::pickColor() : $adjectives[array_rand($adjectives)];
        $noun = $nouns === [] ? PolydockAppInstance::pickAnimal() : $nouns[array_rand($nouns)];

        $uniqueIdLengthBytes = 3; // 6 hex chars
        try {
            $shortUniqueId = bin2hex(random_bytes($uniqueIdLengthBytes));
        } catch (\Exception) {
            // Fallback preserves randomness if secure source is unavailable.
            $shortUniqueId = substr(hash('sha256', uniqid('', true)), 0, $uniqueIdLengthBytes * 2);
        }

        return strtolower("{$prefix}-{$adjective}-{$noun}-{$shortUniqueId}");
    }

    /**
     * App-level fallback word lists, used when the store app config has none.
     * Override in an app class to theme collision variants (e.g. crustaceans).
     *
     * @return array<int, string>
     */
    public static function defaultProjectNamingAdjectives(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public static function defaultProjectNamingNouns(): array
    {
        return [];
    }

    private function setProjectName(PolydockAppInstanceInterface $appInstance, string $projectName): void
    {
        /** @phpstan-ignore-next-line */
        $appInstance->setName($projectName);
        $appInstance->storeKeyValue('lagoon-project-name', $projectName);
        $appInstance->save();
        $this->info("finalizeCustomProjectName: Final project name: {$projectName}");
    }
}
