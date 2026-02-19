<?php

namespace App\Services;

use Composer\Autoload\ClassLoader;
use FreedomtechHosting\PolydockApp\Attributes\PolydockAppInstanceFields;
use FreedomtechHosting\PolydockApp\Attributes\PolydockAppStoreFields;
use FreedomtechHosting\PolydockApp\Attributes\PolydockAppTitle;
use FreedomtechHosting\PolydockApp\PolydockAppInterface;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

/**
 * Discovers concrete classes that implement PolydockAppInterface.
 *
 * Uses Composer's optimized classmap to find candidates, then filters to
 * concrete (non-abstract, non-interface) implementations.
 *
 * Register as a singleton in AppServiceProvider to ensure discovery
 * runs at most once per request.
 *
 * NOTE: The classmap pre-filter scans for classes whose FQCN contains
 * "Polydock". This is a convention-based assumption to avoid triggering
 * autoloading of unrelated vendor classes (which can cause fatal errors
 * when their dependencies are missing, e.g. dev-only test runner traits).
 * If a PolydockAppInterface implementation is published under a namespace
 * that does not contain "Polydock", it will need to be added to the
 * NAMESPACE_FILTERS constant.
 */
class PolydockAppClassDiscovery
{
    /**
     * Substrings that a class name must contain (case-insensitive) to be
     * considered a candidate for discovery. This avoids autoloading
     * the entire classmap (which can trigger fatal errors for dev-only
     * classes with missing dependencies).
     *
     * @var string[]
     */
    private const array NAMESPACE_FILTERS = ['Polydock'];

    /**
     * Cached discovery results.
     *
     * @var array<string, string>|null
     */
    private ?array $cache = null;

    /**
     * Discover all concrete classes that implement PolydockAppInterface.
     *
     * @return array<string, string> FQCN => human-readable label
     */
    public function getAvailableAppClasses(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $classes = [];

        foreach (array_keys($this->getClassMap()) as $className) {
            if (! $this->matchesNamespaceFilter($className)) {
                continue;
            }

            try {
                if (! class_exists($className, true)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);
            } catch (\Throwable) {
                continue;
            }

            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            if (! $reflection->implementsInterface(PolydockAppInterface::class)) {
                continue;
            }

            $label = $this->buildLabel($reflection);
            $classes[$className] = $label;
        }

        ksort($classes);
        $this->cache = $classes;

        return $classes;
    }

    /**
     * Build a human-readable label for the given class.
     *
     * If the class has a #[PolydockAppTitle] attribute, use its title.
     * Otherwise, fall back to "ShortName (Namespace)" format.
     *
     * @param  ReflectionClass<object>  $reflection
     */
    private function buildLabel(ReflectionClass $reflection): string
    {
        $attributes = $reflection->getAttributes(PolydockAppTitle::class);

        if (! empty($attributes)) {
            /** @var PolydockAppTitle $titleAttr */
            $titleAttr = $attributes[0]->newInstance();

            return $titleAttr->title;
        }

        // Fallback: ShortName (Namespace)
        $shortName = $reflection->getShortName();
        $namespace = $reflection->getNamespaceName();

        return "{$shortName} ({$namespace})";
    }

    /**
     * Check whether a given class name is a valid, concrete PolydockAppInterface implementation.
     */
    public function isValidAppClass(string $className): bool
    {
        return isset($this->getAvailableAppClasses()[$className]);
    }

    /**
     * Clear the cached discovery results.
     */
    public function clearCache(): void
    {
        $this->cache = null;
    }

    /**
     * Get the custom store app form schema for a given class.
     * Field names are automatically prefixed with 'app_config_'.
     *
     * @param  string  $className  The fully qualified class name
     * @return array<\Filament\Forms\Components\Component> Array of Filament form components
     */
    public function getStoreAppFormSchema(string $className): array
    {
        if (empty($className)) {
            \Log::debug('getStoreAppFormSchema: Empty class name provided');

            return [];
        }

        if (! $this->isValidAppClass($className)) {
            \Log::debug('getStoreAppFormSchema: Invalid app class', ['className' => $className]);

            return [];
        }

        try {
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(PolydockAppStoreFields::class);

            if (empty($attributes)) {
                \Log::debug('getStoreAppFormSchema: No PolydockAppStoreFields attribute found', ['className' => $className]);

                return [];
            }

            /** @var PolydockAppStoreFields $attr */
            $attr = $attributes[0]->newInstance();
            $methodName = $attr->formMethod;

            if (! method_exists($className, $methodName)) {
                \Log::debug('getStoreAppFormSchema: Method does not exist', [
                    'className' => $className,
                    'methodName' => $methodName,
                ]);

                return [];
            }

            \Log::debug('getStoreAppFormSchema: Calling schema method', [
                'className' => $className,
                'methodName' => $methodName,
            ]);

            $schema = $className::$methodName();

            \Log::debug('getStoreAppFormSchema: Schema retrieved', [
                'className' => $className,
                'schemaCount' => count($schema),
            ]);

            // Prefix all field names with 'app_config_'
            return $this->prefixSchemaFieldNames($schema);
        } catch (\Throwable $e) {
            \Log::error('getStoreAppFormSchema: Exception thrown', [
                'className' => $className,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Get the custom store app infolist schema for a given class.
     * Field names are automatically prefixed with 'app_config_'.
     *
     * @param  string  $className  The fully qualified class name
     * @return array<\Filament\Infolists\Components\Component> Array of Filament infolist components
     */
    public function getStoreAppInfolistSchema(string $className): array
    {
        if (empty($className)) {
            Log::debug('getStoreAppInfolistSchema: Empty class name provided');

            return [];
        }

        if (! $this->isValidAppClass($className)) {
            Log::debug('getStoreAppInfolistSchema: Invalid app class', ['className' => $className]);

            return [];
        }

        try {
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(PolydockAppStoreFields::class);

            if (empty($attributes)) {
                Log::debug('getStoreAppInfolistSchema: No PolydockAppStoreFields attribute found', ['className' => $className]);

                return [];
            }

            /** @var PolydockAppStoreFields $attr */
            $attr = $attributes[0]->newInstance();
            $methodName = $attr->infolistMethod;

            if (! method_exists($className, $methodName)) {
                Log::debug('getStoreAppInfolistSchema: Method does not exist', [
                    'className' => $className,
                    'methodName' => $methodName,
                ]);

                return [];
            }

            Log::debug('getStoreAppInfolistSchema: Calling schema method', [
                'className' => $className,
                'methodName' => $methodName,
            ]);

            $schema = $className::$methodName();

            Log::debug('getStoreAppInfolistSchema: Schema retrieved', [
                'className' => $className,
                'schemaCount' => count($schema),
            ]);

            // Prefix all field names with 'app_config_'
            return $this->prefixSchemaFieldNames($schema);
        } catch (\Throwable $e) {
            Log::error('getStoreAppInfolistSchema: Exception thrown', [
                'className' => $className,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Get field names from a store app form schema (with prefix).
     *
     * @param  string  $className  The fully qualified class name
     * @return array<string> Array of prefixed field names
     */
    public function getStoreAppFormFieldNames(string $className): array
    {
        $schema = $this->getStoreAppFormSchema($className);

        return $this->extractFieldNamesFromSchema($schema);
    }

    /**
     * Get the custom app instance form schema for a given class.
     * Field names are automatically prefixed with 'instance_config_'.
     *
     * @param  string  $className  The fully qualified class name
     * @return array<\Filament\Forms\Components\Component> Array of Filament form components
     */
    public function getAppInstanceFormSchema(string $className): array
    {
        if (empty($className)) {
            Log::debug('getAppInstanceFormSchema: Empty class name provided');

            return [];
        }

        if (! $this->isValidAppClass($className)) {
            Log::debug('getAppInstanceFormSchema: Invalid app class', ['className' => $className]);

            return [];
        }

        try {
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(PolydockAppInstanceFields::class);

            if (empty($attributes)) {
                Log::debug('getAppInstanceFormSchema: No PolydockAppInstanceFields attribute found', ['className' => $className]);

                return [];
            }

            /** @var PolydockAppInstanceFields $attr */
            $attr = $attributes[0]->newInstance();
            $methodName = $attr->formMethod;

            if (! method_exists($className, $methodName)) {
                Log::debug('getAppInstanceFormSchema: Method does not exist', [
                    'className' => $className,
                    'methodName' => $methodName,
                ]);

                return [];
            }

            Log::debug('getAppInstanceFormSchema: Calling schema method', [
                'className' => $className,
                'methodName' => $methodName,
            ]);

            $schema = $className::$methodName();

            Log::debug('getAppInstanceFormSchema: Schema retrieved', [
                'className' => $className,
                'schemaCount' => count($schema),
            ]);

            // Prefix all field names with 'instance_config_'
            return $this->prefixAppInstanceSchemaFieldNames($schema);
        } catch (\Throwable $e) {
            Log::error('getAppInstanceFormSchema: Exception thrown', [
                'className' => $className,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Get the custom app instance infolist schema for a given class.
     * Field names are automatically prefixed with 'instance_config_'.
     *
     * @param  string  $className  The fully qualified class name
     * @return array<\Filament\Infolists\Components\Component> Array of Filament infolist components
     */
    public function getAppInstanceInfolistSchema(string $className): array
    {
        if (empty($className)) {
            Log::debug('getAppInstanceInfolistSchema: Empty class name provided');

            return [];
        }

        if (! $this->isValidAppClass($className)) {
            Log::debug('getAppInstanceInfolistSchema: Invalid app class', ['className' => $className]);

            return [];
        }

        try {
            $reflection = new ReflectionClass($className);
            $attributes = $reflection->getAttributes(PolydockAppInstanceFields::class);

            if (empty($attributes)) {
                Log::debug('getAppInstanceInfolistSchema: No PolydockAppInstanceFields attribute found', ['className' => $className]);

                return [];
            }

            /** @var PolydockAppInstanceFields $attr */
            $attr = $attributes[0]->newInstance();
            $methodName = $attr->infolistMethod;

            if (! method_exists($className, $methodName)) {
                Log::debug('getAppInstanceInfolistSchema: Method does not exist', [
                    'className' => $className,
                    'methodName' => $methodName,
                ]);

                return [];
            }

            Log::debug('getAppInstanceInfolistSchema: Calling schema method', [
                'className' => $className,
                'methodName' => $methodName,
            ]);

            $schema = $className::$methodName();

            Log::debug('getAppInstanceInfolistSchema: Schema retrieved', [
                'className' => $className,
                'schemaCount' => count($schema),
            ]);

            // Prefix all field names with 'instance_config_'
            return $this->prefixAppInstanceSchemaFieldNames($schema);
        } catch (\Throwable $e) {
            Log::error('getAppInstanceInfolistSchema: Exception thrown', [
                'className' => $className,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Get field names from an app instance form schema (with prefix).
     *
     * @param  string  $className  The fully qualified class name
     * @return array<string> Array of prefixed field names
     */
    public function getAppInstanceFormFieldNames(string $className): array
    {
        $schema = $this->getAppInstanceFormSchema($className);

        return $this->extractFieldNamesFromSchema($schema);
    }

    /**
     * Recursively prefix field names in Filament schema components for App Instance.
     *
     * @return array<mixed>
     */
    private function prefixAppInstanceSchemaFieldNames(array $components): array
    {
        $prefix = PolydockAppInstanceFields::FIELD_PREFIX;

        foreach ($components as $component) {
            // Prefix the field name if this component has one
            if (method_exists($component, 'getName') && method_exists($component, 'name')) {
                $name = $component->getName();
                if ($name !== null && ! str_starts_with($name, $prefix)) {
                    $component->name($prefix.$name);
                }
            }

            // Recursively process child schema (for Sections, Grids, etc.)
            if (method_exists($component, 'getChildComponents') && method_exists($component, 'schema')) {
                $children = $component->getChildComponents();
                if (! empty($children)) {
                    $component->schema($this->prefixAppInstanceSchemaFieldNames($children));
                }
            }
        }

        return $components;
    }

    /**
     * Recursively prefix field names in Filament schema components.
     *
     * @return array<mixed>
     */
    private function prefixSchemaFieldNames(array $components): array
    {
        $prefix = PolydockAppStoreFields::FIELD_PREFIX;

        foreach ($components as $component) {
            // Prefix the field name if this component has one
            if (method_exists($component, 'getName') && method_exists($component, 'name')) {
                $name = $component->getName();
                if ($name !== null && ! str_starts_with($name, $prefix)) {
                    $component->name($prefix.$name);
                }
            }

            // Recursively process child schema (for Sections, Grids, etc.)
            if (method_exists($component, 'getChildComponents') && method_exists($component, 'schema')) {
                $children = $component->getChildComponents();
                if (! empty($children)) {
                    $component->schema($this->prefixSchemaFieldNames($children));
                }
            }
        }

        return $components;
    }

    /**
     * Recursively extract field names from Filament schema components.
     *
     * @return array<string>
     */
    private function extractFieldNamesFromSchema(array $components): array
    {
        $names = [];

        foreach ($components as $component) {
            // Get the field name if this component has one
            if (method_exists($component, 'getName')) {
                $name = $component->getName();
                if ($name !== null) {
                    $names[] = $name;
                }
            }

            // Recursively check child schema (for Sections, Grids, etc.)
            if (method_exists($component, 'getChildComponents')) {
                $children = $component->getChildComponents();
                if (! empty($children)) {
                    $names = array_merge($names, $this->extractFieldNamesFromSchema($children));
                }
            }
        }

        return $names;
    }

    /**
     * Check if a field should be stored encrypted based on extraAttributes.
     *
     * @param  mixed  $component
     */
    public function isFieldEncrypted($component): bool
    {
        if (! method_exists($component, 'getExtraAttributes')) {
            return false;
        }

        $extraAttributes = $component->getExtraAttributes();

        return isset($extraAttributes['encrypted']) && $extraAttributes['encrypted'] === true;
    }

    /**
     * Get encryption map for all fields in a schema.
     *
     * @return array<string, bool> fieldName => isEncrypted
     */
    public function getFieldEncryptionMap(array $components): array
    {
        $map = [];

        foreach ($components as $component) {
            if (method_exists($component, 'getName')) {
                $name = $component->getName();
                if ($name !== null) {
                    $map[$name] = $this->isFieldEncrypted($component);
                }
            }

            if (method_exists($component, 'getChildComponents')) {
                $children = $component->getChildComponents();
                if (! empty($children)) {
                    $map = array_merge($map, $this->getFieldEncryptionMap($children));
                }
            }
        }

        return $map;
    }

    /**
     * Check if a class name matches any of the namespace filters.
     */
    private function matchesNamespaceFilter(string $className): bool
    {
        foreach (self::NAMESPACE_FILTERS as $filter) {
            if (stripos($className, $filter) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get Composer's merged class map from all registered loaders.
     *
     * @return array<string, string> className => filePath
     */
    protected function getClassMap(): array
    {
        /** @var ClassLoader[] $loaders */
        $loaders = ClassLoader::getRegisteredLoaders();

        $classMap = [];
        foreach ($loaders as $loader) {
            $classMap = array_merge($classMap, $loader->getClassMap());
        }

        return $classMap;
    }
}
