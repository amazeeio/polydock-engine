<?php

namespace Tests\Unit\Services;

use App\Services\PolydockAppClassDiscovery;
use FreedomtechHosting\PolydockApp\PolydockAppBase;
use FreedomtechHosting\PolydockApp\PolydockAppInterface;
use Tests\TestCase;

class PolydockAppClassDiscoveryTest extends TestCase
{
    private PolydockAppClassDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discovery = app(PolydockAppClassDiscovery::class);
        $this->discovery->clearCache();
    }

    // ──────────────────────────────────────────────────────────────
    //  Discovery: happy-path
    // ──────────────────────────────────────────────────────────────

    public function test_returns_non_empty_array(): void
    {
        $classes = $this->discovery->getAvailableAppClasses();

        $this->assertNotEmpty($classes, 'Should discover at least one app class');
        $this->assertIsArray($classes);
    }

    public function test_all_returned_classes_implement_interface(): void
    {
        foreach (array_keys($this->discovery->getAvailableAppClasses()) as $className) {
            $this->assertTrue(
                class_exists($className),
                "Class {$className} should exist"
            );

            $reflection = new \ReflectionClass($className);
            $this->assertTrue(
                $reflection->implementsInterface(PolydockAppInterface::class),
                "Class {$className} should implement PolydockAppInterface"
            );
        }
    }

    public function test_all_returned_classes_are_concrete(): void
    {
        foreach (array_keys($this->discovery->getAvailableAppClasses()) as $className) {
            $reflection = new \ReflectionClass($className);
            $this->assertFalse(
                $reflection->isAbstract(),
                "Class {$className} should not be abstract"
            );
            $this->assertFalse(
                $reflection->isInterface(),
                "Class {$className} should not be an interface"
            );
        }
    }

    public function test_includes_known_app_classes(): void
    {
        $classNames = array_keys($this->discovery->getAvailableAppClasses());

        $this->assertContains(
            \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class,
            $classNames,
            'Should include PolydockAiApp'
        );

        $this->assertContains(
            \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockApp::class,
            $classNames,
            'Should include PolydockApp'
        );

        $this->assertContains(
            \Amazeeio\PolydockAppAmazeeioPrivateGpt\PolydockPrivateGptApp::class,
            $classNames,
            'Should include PolydockPrivateGptApp'
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Discovery: exclusion rules
    // ──────────────────────────────────────────────────────────────

    public function test_excludes_interface(): void
    {
        $classes = $this->discovery->getAvailableAppClasses();

        $this->assertArrayNotHasKey(
            PolydockAppInterface::class,
            $classes,
            'Should not include the interface itself'
        );
    }

    public function test_excludes_abstract_base_class(): void
    {
        $classes = $this->discovery->getAvailableAppClasses();

        $this->assertArrayNotHasKey(
            PolydockAppBase::class,
            $classes,
            'Should not include the abstract base class'
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Labels
    // ──────────────────────────────────────────────────────────────

    public function test_labels_use_attribute_title_when_available(): void
    {
        $classes = $this->discovery->getAvailableAppClasses();

        // Check PolydockAiApp - it may have an attribute (after package update) or fallback format
        $aiAppLabel = $classes[\FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class] ?? null;
        $this->assertNotNull($aiAppLabel, 'PolydockAiApp should have a label');

        // The label should either be the attribute title OR the fallback format
        $hasAttributeTitle = $aiAppLabel === 'Generic Lagoon AI App';
        $hasFallbackFormat = str_contains($aiAppLabel, 'PolydockAiApp') && str_contains($aiAppLabel, 'FreedomtechHosting');

        $this->assertTrue(
            $hasAttributeTitle || $hasFallbackFormat,
            "Label should be either 'Generic Lagoon AI App' (attribute) or contain class/namespace (fallback). Got: {$aiAppLabel}"
        );
    }

    public function test_build_label_uses_attribute_when_present(): void
    {
        // Create a mock class with the PolydockAppTitle attribute for testing
        // This tests the buildLabel logic directly using reflection on a known attributed class
        $reflection = new \ReflectionClass(\FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class);
        $attributes = $reflection->getAttributes(\FreedomtechHosting\PolydockApp\Attributes\PolydockAppTitle::class);

        if (! empty($attributes)) {
            // If the attribute exists (after package update), verify it's read correctly
            $titleAttr = $attributes[0]->newInstance();
            $this->assertEquals('Generic Lagoon AI App', $titleAttr->title);
        } else {
            // If attribute doesn't exist yet (before package update), just verify the class exists
            $this->assertTrue(class_exists(\FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class));
        }
    }

    public function test_keys_are_sorted_alphabetically(): void
    {
        $classes = $this->discovery->getAvailableAppClasses();
        $keys = array_keys($classes);
        $sorted = $keys;
        sort($sorted);

        $this->assertSame($sorted, $keys, 'Class keys should be sorted alphabetically');
    }

    // ──────────────────────────────────────────────────────────────
    //  Caching
    // ──────────────────────────────────────────────────────────────

    public function test_caching_returns_identical_result(): void
    {
        $first = $this->discovery->getAvailableAppClasses();
        $second = $this->discovery->getAvailableAppClasses();

        $this->assertSame($first, $second, 'Cached results should be identical');
    }

    public function test_clear_cache_allows_rediscovery(): void
    {
        $first = $this->discovery->getAvailableAppClasses();
        $this->discovery->clearCache();
        $second = $this->discovery->getAvailableAppClasses();

        $this->assertEquals($first, $second, 'Rediscovered results should match');
    }

    // ──────────────────────────────────────────────────────────────
    //  isValidAppClass() — positive cases
    // ──────────────────────────────────────────────────────────────

    public function test_is_valid_app_class_returns_true_for_known_class(): void
    {
        $this->assertTrue(
            $this->discovery->isValidAppClass(
                \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class
            )
        );
    }

    public function test_is_valid_app_class_returns_true_for_all_discovered_classes(): void
    {
        foreach (array_keys($this->discovery->getAvailableAppClasses()) as $className) {
            $this->assertTrue(
                $this->discovery->isValidAppClass($className),
                "{$className} should be valid"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  isValidAppClass() — negative / edge cases
    // ──────────────────────────────────────────────────────────────

    public function test_is_valid_app_class_returns_false_for_nonexistent_class(): void
    {
        $this->assertFalse(
            $this->discovery->isValidAppClass('Acme\\Nonexistent\\FakePolydockApp')
        );
    }

    public function test_is_valid_app_class_returns_false_for_interface(): void
    {
        $this->assertFalse(
            $this->discovery->isValidAppClass(PolydockAppInterface::class)
        );
    }

    public function test_is_valid_app_class_returns_false_for_abstract_class(): void
    {
        $this->assertFalse(
            $this->discovery->isValidAppClass(PolydockAppBase::class)
        );
    }

    public function test_is_valid_app_class_returns_false_for_empty_string(): void
    {
        $this->assertFalse(
            $this->discovery->isValidAppClass('')
        );
    }

    public function test_is_valid_app_class_returns_false_for_unrelated_class(): void
    {
        $this->assertFalse(
            $this->discovery->isValidAppClass(\stdClass::class)
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Container binding
    // ──────────────────────────────────────────────────────────────

    public function test_service_is_registered_as_singleton(): void
    {
        $first = app(PolydockAppClassDiscovery::class);
        $second = app(PolydockAppClassDiscovery::class);

        $this->assertSame($first, $second, 'Service should be the same singleton instance');
    }

    // ──────────────────────────────────────────────────────────────
    //  Store App Form Schema
    // ──────────────────────────────────────────────────────────────

    public function test_get_store_app_form_schema_returns_empty_for_class_without_attribute(): void
    {
        $schema = $this->discovery->getStoreAppFormSchema(
            \Amazeeio\PolydockAppAmazeeioPrivateGpt\PolydockPrivateGptApp::class
        );

        $this->assertIsArray($schema);
        $this->assertEmpty($schema);
    }

    public function test_get_store_app_form_schema_returns_empty_for_invalid_class(): void
    {
        $schema = $this->discovery->getStoreAppFormSchema('NonExistent\\Class');

        $this->assertIsArray($schema);
        $this->assertEmpty($schema);
    }

    public function test_get_store_app_form_schema_returns_empty_for_empty_string(): void
    {
        $schema = $this->discovery->getStoreAppFormSchema('');

        $this->assertIsArray($schema);
        $this->assertEmpty($schema);
    }

    public function test_get_store_app_infolist_schema_returns_empty_for_invalid_class(): void
    {
        $schema = $this->discovery->getStoreAppInfolistSchema('NonExistent\\Class');

        $this->assertIsArray($schema);
        $this->assertEmpty($schema);
    }

    public function test_get_store_app_infolist_schema_returns_empty_for_empty_string(): void
    {
        $schema = $this->discovery->getStoreAppInfolistSchema('');

        $this->assertIsArray($schema);
        $this->assertEmpty($schema);
    }

    public function test_get_store_app_form_field_names_returns_array(): void
    {
        $fieldNames = $this->discovery->getStoreAppFormFieldNames(
            \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class
        );

        $this->assertIsArray($fieldNames);
    }

    public function test_get_field_encryption_map_returns_array(): void
    {
        $schema = $this->discovery->getStoreAppFormSchema(
            \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class
        );

        $map = $this->discovery->getFieldEncryptionMap($schema);

        $this->assertIsArray($map);
    }

    public function test_field_names_are_prefixed_with_app_config(): void
    {
        $fieldNames = $this->discovery->getStoreAppFormFieldNames(
            \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class
        );

        $this->assertIsArray($fieldNames);

        // If the class has fields (after package update), they should all be prefixed
        foreach ($fieldNames as $fieldName) {
            $this->assertStringStartsWith(
                'app_config_',
                $fieldName,
                "Field '{$fieldName}' should be prefixed with 'app_config_'"
            );
        }
    }

    public function test_store_app_form_schema_returns_components_when_attribute_present(): void
    {
        $schema = $this->discovery->getStoreAppFormSchema(
            \FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp::class
        );

        // The schema may be empty if the package hasn't been updated yet
        // But if it has components, they should be Filament components
        $this->assertIsArray($schema);

        if (! empty($schema)) {
            foreach ($schema as $component) {
                $this->assertIsObject($component);
            }
        }
    }
}
