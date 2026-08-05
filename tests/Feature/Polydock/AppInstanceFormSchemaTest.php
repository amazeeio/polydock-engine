<?php

declare(strict_types=1);

namespace Tests\Feature\Polydock;

use App\Polydock\Apps\AmazeeClaw\PolydockAmazeeClawAiApp;
use App\Polydock\Apps\DependencyTrack\PolydockDependencyTrackApp;
use App\Polydock\Core\Attributes\PolydockAppInstanceFields;
use App\Services\PolydockAppClassDiscovery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression cover for app-instance schema discovery.
 *
 * getAppInstanceFormSchema() wraps everything in catch (Throwable) and returns
 * [] on failure, so a broken schema walk looks exactly like "this app declares
 * no instance fields" — the admin panel just renders nothing. These assert the
 * fields actually come back, which is the only way that failure is visible.
 */
class AppInstanceFormSchemaTest extends TestCase
{
    private PolydockAppClassDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discovery = app(PolydockAppClassDiscovery::class);
    }

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function appsWithInstanceFields(): array
    {
        return [
            'AmazeeClaw' => [PolydockAmazeeClawAiApp::class, 'openclaw_default_model'],
            'DependencyTrack' => [PolydockDependencyTrackApp::class, 'lagoon_organisation'],
        ];
    }

    /**
     * @param  class-string  $appClass
     */
    #[DataProvider('appsWithInstanceFields')]
    public function test_declared_instance_fields_survive_discovery(string $appClass, string $expectedField): void
    {
        $schema = $this->discovery->getAppInstanceFormSchema($appClass);

        $this->assertNotEmpty(
            $schema,
            $appClass.' declares instance fields but discovery returned none — the schema walk is swallowing an error.',
        );

        $names = array_map(static fn ($component) => $component->getName(), $schema);

        $this->assertContains(PolydockAppInstanceFields::FIELD_PREFIX.$expectedField, $names);
    }

    /**
     * @param  class-string  $appClass
     */
    #[DataProvider('appsWithInstanceFields')]
    public function test_field_names_are_prefixed(string $appClass, string $expectedField): void
    {
        $names = $this->discovery->getAppInstanceFormFieldNames($appClass);

        $this->assertNotEmpty($names);

        foreach ($names as $name) {
            $this->assertStringStartsWith(PolydockAppInstanceFields::FIELD_PREFIX, $name);
        }

        $this->assertContains(PolydockAppInstanceFields::FIELD_PREFIX.$expectedField, $names);
    }

    public function test_encryption_map_covers_every_discovered_field(): void
    {
        $schema = $this->discovery->getAppInstanceFormSchema(PolydockAmazeeClawAiApp::class);
        $map = $this->discovery->getFieldEncryptionMap($schema);

        // Built by the same schema walk — if it silently yields nothing, fields
        // that should be encrypted at rest simply would not be.
        $this->assertNotEmpty($map);
        $this->assertCount(count($schema), $map);
    }

    public function test_unknown_class_yields_no_fields(): void
    {
        $this->assertSame([], $this->discovery->getAppInstanceFormSchema('App\\Does\\Not\\Exist'));
        $this->assertSame([], $this->discovery->getAppInstanceFormSchema(''));
    }
}
