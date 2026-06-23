<?php

namespace Tests\Feature;

use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\PolydockStoreApp;
use App\Models\UserGroup;
use App\Polydock\Apps\AmazeeClaw\PolydockAmazeeClawAiApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TestablePolydockAmazeeClawAiApp extends PolydockAmazeeClawAiApp
{
    public array $injectedVariables = [];

    #[\Override]
    public function addOrUpdateLagoonProjectVariable($appInstance, $variableName, $variableValue, $variableScope): void
    {
        $this->injectedVariables[$variableName] = $variableValue;
    }
}

class AmazeeClawConfigTest extends TestCase
{
    use RefreshDatabase;

    private function createAppInstance(array $config = []): PolydockAppInstance
    {
        $store = PolydockStore::factory()->create();
        $storeApp = PolydockStoreApp::factory()->create([
            'polydock_store_id' => $store->id,
            'app_config' => $config['app_config'] ?? [],
        ]);
        $userGroup = UserGroup::factory()->create();

        $instance = new PolydockAppInstance;
        $instance->fill([
            'polydock_store_app_id' => $storeApp->id,
            'user_group_id' => $userGroup->id,
            'name' => 'test-instance',
            'app_type' => PolydockAmazeeClawAiApp::class,
            'status' => PolydockAppInstanceStatus::RUNNING_HEALTHY_CLAIMED,
        ]);
        $instance->data = $config['data'] ?? [];

        $instance->uuid = Str::uuid()->toString();
        $instance->saveQuietly();

        return $instance;
    }

    public function test_form_schemas_contain_key_mode()
    {
        $storeSchema = PolydockAmazeeClawAiApp::getStoreAppFormSchema();
        $instanceSchema = PolydockAmazeeClawAiApp::getAppInstanceFormSchema();

        $hasKeyModeInStore = false;
        foreach ($storeSchema as $component) {
            if ($component->getName() === 'amazeeai_key_mode') {
                $hasKeyModeInStore = true;
            }
        }

        $hasKeyModeInInstance = false;
        foreach ($instanceSchema as $component) {
            if ($component->getName() === 'amazeeai_key_mode') {
                $hasKeyModeInInstance = true;
            }
        }

        $this->assertTrue($hasKeyModeInStore, 'Store App form schema should contain amazeeai_key_mode');
        $this->assertFalse($hasKeyModeInInstance, 'App Instance form schema should NOT contain amazeeai_key_mode');
    }

    public function test_key_mode_resolution()
    {
        $app = new PolydockAmazeeClawAiApp('Test App', 'Description', 'Author', 'https://example.com', 'support@example.com');

        // 1. Default fallback
        $instanceDefault = $this->createAppInstance();
        $this->assertEquals('manual', $app->resolveAmazeeAiKeyMode($instanceDefault));

        // 2. Resolve from app_config (store app level)
        $instanceStoreApp = $this->createAppInstance([
            'app_config' => ['amazeeai_key_mode' => 'auto'],
        ]);
        $this->assertEquals('auto', $app->resolveAmazeeAiKeyMode($instanceStoreApp));

        // 3. Resolve from instance config
        $instanceConfig = $this->createAppInstance([
            'data' => [
                'instance_config_amazeeai_key_mode' => 'auto',
            ],
        ]);
        $this->assertEquals('auto', $app->resolveAmazeeAiKeyMode($instanceConfig));
    }

    public function test_injects_manual_credentials()
    {
        $app = new TestablePolydockAmazeeClawAiApp('Test App', 'Description', 'Author', 'https://example.com', 'support@example.com');

        $instance = $this->createAppInstance([
            'data' => [
                'instance_config_amazeeai_key_mode' => 'manual',
                'secret' => [
                    'llm_key' => 'manual-api-key',
                    'llm_url' => 'https://manual.api.url',
                    'vector_db_host' => 'manual-db-host',
                    'vector_db_user' => 'manual-db-user',
                    'vector_db_pass' => 'manual-db-pass',
                    'vector_db_name' => 'manual-db-name',
                ],
            ],
        ]);

        $envVars = $app->provisionAndInjectManualAmazeeAiCredentials($instance);

        $this->assertEquals('manual-api-key', $envVars['AMAZEEAI_API_KEY']);
        $this->assertEquals('https://manual.api.url', $envVars['AMAZEEAI_BASE_URL']);
        $this->assertEquals('manual-db-host', $envVars['AMAZEEAI_VECTOR_DB_HOST']);
        $this->assertEquals('manual-db-user', $envVars['AMAZEEAI_VECTOR_DB_USER']);
        $this->assertEquals('manual-db-pass', $envVars['AMAZEEAI_VECTOR_DB_PASS']);
        $this->assertEquals('manual-db-name', $envVars['AMAZEEAI_VECTOR_DB_NAME']);

        $this->assertEquals('manual-api-key', $app->injectedVariables['AMAZEEAI_API_KEY']);
        $this->assertEquals('https://manual.api.url', $app->injectedVariables['AMAZEEAI_BASE_URL']);
    }

    public function test_injects_auto_generated_credentials()
    {
        $app = new TestablePolydockAmazeeClawAiApp('Test App', 'Description', 'Author', 'https://example.com', 'support@example.com');

        $instance = $this->createAppInstance([
            'data' => [
                'instance_config_amazeeai_key_mode' => 'auto',
                'amazee-ai-generated-credentials' => json_encode([
                    'litellm_token' => 'auto-api-key',
                    'litellm_api_url' => 'https://auto.api.url',
                    'database_host' => 'auto-db-host',
                    'database_username' => 'auto-db-user',
                    'database_password' => 'auto-db-pass',
                    'database_name' => 'auto-db-name',
                ]),
            ],
        ]);

        $envVars = $app->provisionAndInjectManualAmazeeAiCredentials($instance);

        $this->assertEquals('auto-api-key', $envVars['AMAZEEAI_API_KEY']);
        $this->assertEquals('https://auto.api.url', $envVars['AMAZEEAI_BASE_URL']);
        $this->assertEquals('auto-db-host', $envVars['AMAZEEAI_VECTOR_DB_HOST']);
        $this->assertEquals('5432', $envVars['AMAZEEAI_VECTOR_DB_PORT']);
        $this->assertEquals('auto-db-user', $envVars['AMAZEEAI_VECTOR_DB_USER']);
        $this->assertEquals('auto-db-pass', $envVars['AMAZEEAI_VECTOR_DB_PASS']);
        $this->assertEquals('auto-db-name', $envVars['AMAZEEAI_VECTOR_DB_NAME']);

        $this->assertEquals('auto-api-key', $app->injectedVariables['AMAZEEAI_API_KEY']);
        $this->assertEquals('https://auto.api.url', $app->injectedVariables['AMAZEEAI_BASE_URL']);
    }
}
