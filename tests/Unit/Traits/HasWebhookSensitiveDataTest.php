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
        };
    }

    public function test_get_sensitive_data_keys_returns_defaults()
    {
        $keys = $this->traitObject->getSensitiveDataKeys();

        $this->assertIsArray($keys);
        $this->assertContains('private_key', $keys);
        $this->assertContains('secret', $keys);
    }

    public function test_register_sensitive_data_keys_merges_new_keys()
    {
        $this->traitObject->registerSensitiveDataKeys('new_key');
        $keys = $this->traitObject->getSensitiveDataKeys();

        $this->assertContains('new_key', $keys);
        $this->assertContains('private_key', $keys);
    }

    public function test_register_sensitive_data_keys_with_array()
    {
        $this->traitObject->registerSensitiveDataKeys(['key1', 'key2']);
        $keys = $this->traitObject->getSensitiveDataKeys();

        $this->assertContains('key1', $keys);
        $this->assertContains('key2', $keys);
        $this->assertContains('private_key', $keys);
    }

    public function test_should_filter_key_exact_match()
    {
        $sensitiveKeys = ['password'];
        $this->assertTrue($this->traitObject->shouldFilterKey('password', $sensitiveKeys));
        $this->assertTrue($this->traitObject->shouldFilterKey('PASSWORD', $sensitiveKeys));
        $this->assertFalse($this->traitObject->shouldFilterKey('username', $sensitiveKeys));
    }

    public function test_should_filter_key_regex_match()
    {
        $sensitiveKeys = ['/^.*_key.*$/'];
        $this->assertTrue($this->traitObject->shouldFilterKey('api_key', $sensitiveKeys));
        $this->assertTrue($this->traitObject->shouldFilterKey('some_key_here', $sensitiveKeys));
        $this->assertFalse($this->traitObject->shouldFilterKey('token', $sensitiveKeys));
    }

    public function test_should_filter_key_case_insensitive_regex()
    {
        $sensitiveKeys = ['/^.*_key.*$/'];
        $this->assertTrue($this->traitObject->shouldFilterKey('AMAZEEAI_API_KEY', $sensitiveKeys));
    }
}
