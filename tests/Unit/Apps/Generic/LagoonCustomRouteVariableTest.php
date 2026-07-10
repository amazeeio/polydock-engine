<?php

namespace Tests\Unit\Apps\Generic;

use App\Events\PolydockAppInstanceCreatedWithNewStatus;
use App\Events\PolydockAppInstanceStatusChanged;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Polydock\Apps\Generic\Traits\Create\PostCreateAppInstanceTrait;
use App\Polydock\Core\PolydockAppInstanceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LagoonCustomRouteVariableTest extends TestCase
{
    use RefreshDatabase;

    private function makeInstance(array $appConfig): PolydockAppInstance
    {
        Event::fake([
            PolydockAppInstanceCreatedWithNewStatus::class,
            PolydockAppInstanceStatusChanged::class,
        ]);

        $store = PolydockStore::factory()->create(['lagoon_deploy_project_prefix' => 'testapp']);
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'lagoon_deploy_branch' => 'prod',
            'app_config' => $appConfig,
        ]);

        return PolydockAppInstance::create([
            'polydock_store_app_id' => $storeApp->id,
            'user_group_id' => null,
            'config' => [],
        ]);
    }

    private function makeHarness(): object
    {
        return new class
        {
            use PostCreateAppInstanceTrait;

            /** @var array<int, array{name: string, value: string, scope: string}> */
            public array $variables = [];

            public function addOrUpdateLagoonProjectVariable(PolydockAppInstanceInterface $appInstance, $variableName, $variableValue, $variableScope): void
            {
                $this->variables[] = ['name' => $variableName, 'value' => $variableValue, 'scope' => $variableScope];
            }

            public function info(string $message, array $context = []): void {}

            public function injectRoute(PolydockAppInstanceInterface $appInstance, string $projectName): void
            {
                $this->addLagoonCustomRouteVariable($appInstance, $projectName);
            }
        };
    }

    public function test_no_variable_is_set_without_custom_route_config(): void
    {
        $instance = $this->makeInstance([]);
        $harness = $this->makeHarness();

        $harness->injectRoute($instance, 'testapp-red-lobster-abc123');

        $this->assertEmpty($harness->variables);
    }

    public function test_lagoon_routes_json_is_set_from_custom_route_config(): void
    {
        $instance = $this->makeInstance([
            'lagoon_custom_route_enabled' => true,
            'lagoon_custom_route_domain_pattern' => '{project}.{environment}.example.amazee.io',
            'lagoon_custom_route_service' => 'anythingllm',
            'lagoon_custom_route_annotations' => [
                'nginx.ingress.kubernetes.io/proxy-body-size' => '0',
                'nginx.ingress.kubernetes.io/proxy-read-timeout' => '600',
            ],
        ]);
        $harness = $this->makeHarness();

        $harness->injectRoute($instance, 'Testapp-Red-Lobster-abc123');

        $this->assertCount(1, $harness->variables);
        $this->assertEquals('LAGOON_ROUTES_JSON', $harness->variables[0]['name']);
        $this->assertEquals('BUILD', $harness->variables[0]['scope']);

        $decoded = json_decode(base64_decode($harness->variables[0]['value']), true);

        $this->assertEquals([
            'routes' => [
                [
                    'domain' => 'testapp-red-lobster-abc123.prod.example.amazee.io',
                    'service' => 'anythingllm',
                    'tls-acme' => true,
                    'annotations' => [
                        'nginx.ingress.kubernetes.io/proxy-body-size' => '0',
                        'nginx.ingress.kubernetes.io/proxy-read-timeout' => '600',
                    ],
                ],
            ],
        ], $decoded);
    }

    public function test_empty_annotations_encode_as_json_object(): void
    {
        $instance = $this->makeInstance([
            'lagoon_custom_route_enabled' => true,
            'lagoon_custom_route_domain_pattern' => '{project}.example.amazee.io',
            'lagoon_custom_route_service' => 'anythingllm',
        ]);
        $harness = $this->makeHarness();

        $harness->injectRoute($instance, 'testapp-red-lobster-abc123');

        $json = base64_decode($harness->variables[0]['value']);
        $this->assertStringContainsString('"annotations":{}', $json);
    }
}
