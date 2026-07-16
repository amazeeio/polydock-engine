<?php

namespace Tests\Feature;

use App\Polydock\Apps\AmazeeClaw\Enums\AmazeeAiKeyMode;
use App\Polydock\Apps\AmazeeClaw\PolydockAmazeeClawAiApp;
use App\Polydock\Core\Enums\PolydockAppInstanceStatus;
use App\Polydock\Core\PolydockAppInstanceInterface;
use App\Polydock\Core\PolydockAppInterface;
use App\Polydock\Core\PolydockAppLoggerInterface;
use App\Polydock\Core\PolydockEngineInterface;
use Tests\TestCase;

class DoublePolydockAppInstance implements PolydockAppInstanceInterface
{
    public $storeApp;

    public $data = [];

    public function __construct($storeApp = null, array $data = [])
    {
        $this->storeApp = $storeApp;
        $this->data = $data;
    }

    public function getKeyValue(string $key): mixed
    {
        if ($key === 'secret') {
            return $this->data['secret'] ?? [];
        }
        if ($key === 'amazee-ai-generated-credentials') {
            return $this->data['amazee-ai-generated-credentials'] ?? null;
        }

        return $this->data[$key] ?? '';
    }

    public function getPolydockVariableValue(string $key, $default = '')
    {
        return $this->data[$key] ?? $default;
    }

    public function setApp(PolydockAppInterface $app): self
    {
        return $this;
    }

    public function getApp(): PolydockAppInterface
    {
        throw new \Exception;
    }

    public function setName(string $name): self
    {
        return $this;
    }

    public function getName(): string
    {
        return '';
    }

    public function setAppType(string $appType): self
    {
        return $this;
    }

    public function getAppType(): string
    {
        return '';
    }

    public function getStatus(): PolydockAppInstanceStatus
    {
        throw new \Exception;
    }

    public function setStatus(PolydockAppInstanceStatus $status, string $statusMessage = ''): self
    {
        return $this;
    }

    public function setStatusMessage(string $statusMessage): self
    {
        return $this;
    }

    public function getStatusMessage(): string
    {
        return '';
    }

    public function storeKeyValue(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function deleteKeyValue(string $key): self
    {
        unset($this->data[$key]);

        return $this;
    }

    public function info(string $message, array $context = []): self
    {
        return $this;
    }

    public function error(string $message, array $context = []): self
    {
        return $this;
    }

    public function warning(string $message, array $context = []): self
    {
        return $this;
    }

    public function debug(string $message, array $context = []): self
    {
        return $this;
    }

    public function getLogger(): PolydockAppLoggerInterface
    {
        throw new \Exception;
    }

    public function setLogger(PolydockAppLoggerInterface $logger): self
    {
        return $this;
    }

    public function setEngine(PolydockEngineInterface $engine): self
    {
        return $this;
    }

    public function getEngine(): PolydockEngineInterface
    {
        throw new \Exception;
    }

    public function generateUniqueProjectName(string $prefix): string
    {
        return '';
    }

    public function save(array $options = []) {}

    public function setAppUrl(string $url, ?string $oneTimeLoginUrl = null, ?int $numberOfHoursForOneTimeLoginUrl = 24): self
    {
        return $this;
    }

    public function setOneTimeLoginUrl(string $url, int $numberOfHours = 24, bool $setOnlyDontSave = false): self
    {
        return $this;
    }

    public function getGeneratedAppAdminUsername(): string
    {
        return '';
    }

    public function getGeneratedAppAdminPassword(): string
    {
        return '';
    }
}

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
    private function createAppInstance(array $config = []): DoublePolydockAppInstance
    {
        $storeApp = new \stdClass;
        $storeApp->app_config = $config['app_config'] ?? [];

        return new DoublePolydockAppInstance($storeApp, $config['data'] ?? []);
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

        // 1. Default fallback (legacy empty -> injected)
        $instanceDefault = $this->createAppInstance();
        $this->assertEquals(AmazeeAiKeyMode::Injected, $app->resolveAmazeeAiKeyMode($instanceDefault));

        // 2. Resolve from app_config (store app level); legacy 'auto' -> anonymous
        $instanceStoreApp = $this->createAppInstance([
            'app_config' => ['amazeeai_key_mode' => 'auto'],
        ]);
        $this->assertEquals(AmazeeAiKeyMode::Anonymous, $app->resolveAmazeeAiKeyMode($instanceStoreApp));

        // 3. Resolve from instance config; new 'user' value
        $instanceConfig = $this->createAppInstance([
            'data' => [
                'instance_config_amazeeai_key_mode' => 'user',
            ],
        ]);
        $this->assertEquals(AmazeeAiKeyMode::User, $app->resolveAmazeeAiKeyMode($instanceConfig));
    }

    public function test_key_mode_from_storage_maps_legacy_and_new_values()
    {
        $this->assertEquals(AmazeeAiKeyMode::Anonymous, AmazeeAiKeyMode::fromStorage('auto'));
        $this->assertEquals(AmazeeAiKeyMode::Anonymous, AmazeeAiKeyMode::fromStorage('anonymous'));
        $this->assertEquals(AmazeeAiKeyMode::Injected, AmazeeAiKeyMode::fromStorage('manual'));
        $this->assertEquals(AmazeeAiKeyMode::Injected, AmazeeAiKeyMode::fromStorage('injected'));
        $this->assertEquals(AmazeeAiKeyMode::Injected, AmazeeAiKeyMode::fromStorage(''));
        $this->assertEquals(AmazeeAiKeyMode::Injected, AmazeeAiKeyMode::fromStorage(null));
        $this->assertEquals(AmazeeAiKeyMode::User, AmazeeAiKeyMode::fromStorage('user'));

        $this->assertTrue(AmazeeAiKeyMode::Anonymous->isAutoGenerated());
        $this->assertTrue(AmazeeAiKeyMode::User->isAutoGenerated());
        $this->assertFalse(AmazeeAiKeyMode::Injected->isAutoGenerated());
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

    public function test_build_claim_script_with_secure_stdin_variables()
    {
        $app = new TestablePolydockAmazeeClawAiApp('Test App', 'Description', 'Author', 'https://example.com', 'support@example.com');

        $claimScript = 'node claim.js';
        $envVars = [
            'AMAZEEAI_API_KEY' => 'sensitive-token-here',
            'AMAZEEAI_VECTOR_DB_PASS' => 'db-password-here',
        ];

        // Call the protected method using reflection
        $reflection = new \ReflectionClass($app);
        $method = $reflection->getMethod('buildClaimScriptWithInlineEnvironmentVariables');
        $method->setAccessible(true);

        $result = $method->invoke($app, $claimScript, $envVars);

        // Verify that the actual command does NOT contain any of the sensitive secrets
        $this->assertStringNotContainsString('sensitive-token-here', $result);
        $this->assertStringNotContainsString('db-password-here', $result);

        // Verify it sets up the secure stdin redirection and sourcing logic
        $this->assertStringContainsString('umask 077', $result);
        $this->assertStringContainsString('cat > /tmp/.claw_env', $result);
        $this->assertStringContainsString('. /tmp/.claw_env', $result);
        $this->assertStringContainsString('rm -f /tmp/.claw_env', $result);
        $this->assertStringContainsString($claimScript, $result);
    }
}
