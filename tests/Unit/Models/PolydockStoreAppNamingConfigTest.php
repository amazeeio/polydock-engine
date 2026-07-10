<?php

namespace Tests\Unit\Models;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Jobs\EnsureUnallocatedAppInstancesJob;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PolydockStoreAppNamingConfigTest extends TestCase
{
    use RefreshDatabase;

    private function makeStoreApp(array $appConfig = [], array $attributes = []): PolydockStoreApp
    {
        $store = PolydockStore::factory()->create(['lagoon_deploy_project_prefix' => 'testapp']);

        return PolydockStoreApp::factory()->create($attributes + [
            'polydock_store_id' => $store->id,
            'app_config' => $appConfig,
        ]);
    }

    public function test_naming_mode_defaults_to_pattern_and_supports_pre_warming(): void
    {
        $app = $this->makeStoreApp();

        $this->assertEquals(PolydockStoreApp::PROJECT_NAMING_MODE_PATTERN, $app->project_naming_mode);
        $this->assertTrue($app->supports_pre_warming);
    }

    public function test_custom_naming_mode_disables_pre_warming(): void
    {
        $app = $this->makeStoreApp(
            ['project_naming_mode' => PolydockStoreApp::PROJECT_NAMING_MODE_CUSTOM],
            ['target_unallocated_app_instances' => 3],
        );

        $this->assertFalse($app->supports_pre_warming);
        $this->assertFalse($app->needs_more_unallocated_instances);
    }

    public function test_word_lists_are_cleaned(): void
    {
        $app = $this->makeStoreApp([
            'project_naming_adjectives' => ['Snappy', ' zesty ', 'bad word!', '', null],
            'project_naming_nouns' => ['Lobster'],
        ]);

        $this->assertEquals(['snappy', 'zesty', 'badword'], $app->project_naming_adjectives);
        $this->assertEquals(['lobster'], $app->project_naming_nouns);
    }

    public function test_instance_names_use_store_app_word_lists(): void
    {
        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);

        $app = $this->makeStoreApp([
            'project_naming_adjectives' => ['snappy'],
            'project_naming_nouns' => ['lobster'],
        ]);

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $app->id,
            'user_group_id' => null,
            'config' => [],
        ]);

        $this->assertMatchesRegularExpression('/^testapp-snappy-lobster-[0-9a-f]+$/', $instance->name);
    }

    public function test_instance_names_fall_back_to_generic_word_lists(): void
    {
        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);

        $app = $this->makeStoreApp();

        $instance = PolydockAppInstance::create([
            'polydock_store_app_id' => $app->id,
            'user_group_id' => null,
            'config' => [],
        ]);

        $this->assertMatchesRegularExpression('/^testapp-[a-z]+-[a-z]+-[0-9a-f]+$/', $instance->name);
    }

    public function test_pre_warm_job_skips_custom_named_apps(): void
    {
        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);

        $customApp = $this->makeStoreApp(
            ['project_naming_mode' => PolydockStoreApp::PROJECT_NAMING_MODE_CUSTOM],
            ['target_unallocated_app_instances' => 2],
        );

        (new EnsureUnallocatedAppInstancesJob)->handle();

        $this->assertEquals(0, $customApp->instances()->count());
    }

    public function test_custom_route_config_is_null_when_disabled_or_incomplete(): void
    {
        $this->assertNull($this->makeStoreApp()->lagoon_custom_route_config);

        $this->assertNull($this->makeStoreApp([
            'lagoon_custom_route_enabled' => true,
            'lagoon_custom_route_domain_pattern' => '',
            'lagoon_custom_route_service' => 'anythingllm',
        ])->lagoon_custom_route_config);

        $this->assertNull($this->makeStoreApp([
            'lagoon_custom_route_enabled' => true,
            'lagoon_custom_route_domain_pattern' => '{project}.example.com',
            'lagoon_custom_route_service' => '',
        ])->lagoon_custom_route_config);
    }

    public function test_custom_route_config_returns_cleaned_values(): void
    {
        $app = $this->makeStoreApp([
            'lagoon_custom_route_enabled' => true,
            'lagoon_custom_route_domain_pattern' => ' {project}.example.com ',
            'lagoon_custom_route_service' => 'anythingllm',
            'lagoon_custom_route_annotations' => [
                'nginx.ingress.kubernetes.io/proxy-body-size' => '0',
                'nginx.ingress.kubernetes.io/proxy-read-timeout' => ' 600 ',
                '' => 'ignored',
                'ignored-empty-value' => '',
            ],
        ]);

        $this->assertEquals([
            'domain_pattern' => '{project}.example.com',
            'service' => 'anythingllm',
            'annotations' => [
                'nginx.ingress.kubernetes.io/proxy-body-size' => '0',
                'nginx.ingress.kubernetes.io/proxy-read-timeout' => '600',
            ],
        ], $app->lagoon_custom_route_config);
    }
}
