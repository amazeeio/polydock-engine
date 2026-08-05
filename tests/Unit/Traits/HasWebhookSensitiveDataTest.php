<?php

namespace Tests\Unit\Traits;

use App\Traits\HasWebhookSensitiveData;
use PHPUnit\Framework\TestCase;

class HasWebhookSensitiveDataTest extends TestCase
{
    private $traitObject;

    protected function setUp(): void
    {
        $this->traitObject = new class
        {
            use HasWebhookSensitiveData;

            public $sensitiveDataKeys;

            /** @var array<string, mixed> */
            public $data = [];
        };
    }

    public function test_get_sensitive_data_keys_returns_defaults(): void
    {
        $keys = $this->traitObject->getSensitiveDataKeys();

        $this->assertIsArray($keys);
        $this->assertContains('private_key', $keys);
        $this->assertContains('secret', $keys);
    }

    public function test_register_sensitive_data_keys_merges_new_keys(): void
    {
        $this->traitObject->registerSensitiveDataKeys('new_key');
        $keys = $this->traitObject->getSensitiveDataKeys();

        $this->assertContains('new_key', $keys);
        $this->assertContains('private_key', $keys);
    }

    public function test_register_sensitive_data_keys_with_array(): void
    {
        $this->traitObject->registerSensitiveDataKeys(['key1', 'key2']);
        $keys = $this->traitObject->getSensitiveDataKeys();

        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
        $this->assertContains('private_key', $keys);
    }

    public function test_should_filter_key_exact_match(): void
    {
        $sensitiveKeys = ['password'];
        $this->assertTrue($this->traitObject->shouldFilterKey('password', $sensitiveKeys));
        $this->assertTrue($this->traitObject->shouldFilterKey('PASSWORD', $sensitiveKeys));
        $this->assertFalse($this->traitObject->shouldFilterKey('username', $sensitiveKeys));
    }

    public function test_should_filter_key_regex_match(): void
    {
        $sensitiveKeys = ['/^.*_key.*$/'];
        $this->assertTrue($this->traitObject->shouldFilterKey('api_key', $sensitiveKeys));
        $this->assertTrue($this->traitObject->shouldFilterKey('some_key_here', $sensitiveKeys));
        $this->assertFalse($this->traitObject->shouldFilterKey('token', $sensitiveKeys));
    }

    public function test_should_filter_key_case_insensitive_regex(): void
    {
        $sensitiveKeys = ['/^.*_key.*$/'];
        $this->assertTrue($this->traitObject->shouldFilterKey('AMAZEEAI_API_KEY', $sensitiveKeys));
    }

    public function test_get_webhook_safe_data_includes_credentials_by_default(): void
    {
        $this->traitObject->data = [
            'lagoon-generate-app-admin-password' => 'hunter2',
            'lagoon-generate-app-admin-username' => 'admin',
            'user-email' => 'someone@example.com',
            'some-api-token' => 'should-be-redacted',
            'app-url' => 'https://example.com',
        ];

        $safe = $this->traitObject->getWebhookSafeData();

        $this->assertSame('hunter2', $safe['lagoon-generate-app-admin-password']);
        $this->assertSame('admin', $safe['lagoon-generate-app-admin-username']);
        $this->assertSame('someone@example.com', $safe['user-email']);
        $this->assertSame('https://example.com', $safe['app-url']);
        $this->assertArrayNotHasKey('some-api-token', $safe);
    }

    public function test_get_webhook_safe_data_drops_credentials_when_not_included(): void
    {
        $this->traitObject->data = [
            'lagoon-generate-app-admin-password' => 'hunter2',
            'lagoon-generate-app-admin-username' => 'admin',
            'user-email' => 'someone@example.com',
            'app-url' => 'https://example.com',
        ];

        $safe = $this->traitObject->getWebhookSafeData('data', false);

        $this->assertArrayNotHasKey('lagoon-generate-app-admin-password', $safe);
        $this->assertArrayNotHasKey('lagoon-generate-app-admin-username', $safe);
        $this->assertSame('someone@example.com', $safe['user-email']);
        $this->assertSame('https://example.com', $safe['app-url']);
    }

    public function test_get_webhook_safe_data_reads_the_named_attribute(): void
    {
        $object = new class
        {
            use HasWebhookSensitiveData;

            /** @var list<string>|null */
            public $sensitiveDataKeys;

            /** @var array<string, mixed> */
            public $result_data = [
                'lagoon-generate-app-admin-password' => 'hunter2',
                'amazee-ai-backend-token' => 'should-be-redacted',
                'app-url' => 'https://example.com',
            ];
        };

        $safe = $object->getWebhookSafeData('result_data', false);

        $this->assertArrayNotHasKey('lagoon-generate-app-admin-password', $safe);
        $this->assertArrayNotHasKey('amazee-ai-backend-token', $safe);
        $this->assertSame('https://example.com', $safe['app-url']);

        $withCredentials = $object->getWebhookSafeData('result_data');
        $this->assertSame('hunter2', $withCredentials['lagoon-generate-app-admin-password']);
    }
}
