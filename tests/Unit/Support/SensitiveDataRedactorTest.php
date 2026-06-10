<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;

class SensitiveDataRedactorTest extends TestCase
{
    public function test_replaces_known_exact_match_keys(): void
    {
        $data = [
            'password' => 'hunter2',
            'name' => 'safe-value',
        ];

        $result = SensitiveDataRedactor::redact($data);

        $this->assertEquals(SensitiveDataRedactor::REDACTED_VALUE, $result['password']);
        $this->assertEquals('safe-value', $result['name']);
    }

    public function test_replaces_regex_pattern_matches(): void
    {
        $data = [
            'db_api_key' => 'sk-12345',
            'lagoon_ssh_private' => 'BEGIN RSA...',
            'some_normal_field' => 'hello',
        ];

        $result = SensitiveDataRedactor::redact($data);

        $this->assertEquals(SensitiveDataRedactor::REDACTED_VALUE, $result['db_api_key']);
        $this->assertEquals(SensitiveDataRedactor::REDACTED_VALUE, $result['lagoon_ssh_private']);
        $this->assertEquals('hello', $result['some_normal_field']);
    }

    public function test_does_not_mutate_input_array(): void
    {
        $data = ['api_key' => 'original-value', 'name' => 'test'];
        $original = $data;

        SensitiveDataRedactor::redact($data);

        $this->assertSame($original, $data);
    }

    public function test_preserves_non_sensitive_values(): void
    {
        $data = [
            'email' => 'user@example.com',
            'status' => 'active',
            'count' => 42,
            'enabled' => true,
        ];

        $result = SensitiveDataRedactor::redact($data);

        $this->assertEquals($data, $result);
    }

    public function test_handles_nested_arrays_recursively(): void
    {
        $data = [
            'config' => [
                'api_key' => 'secret',
                'host' => 'localhost',
            ],
            'name' => 'test',
        ];

        $result = SensitiveDataRedactor::redact($data);

        $this->assertEquals(SensitiveDataRedactor::REDACTED_VALUE, $result['config']['api_key']);
        $this->assertEquals('localhost', $result['config']['host']);
        $this->assertEquals('test', $result['name']);
    }

    public function test_handles_empty_array(): void
    {
        $this->assertSame([], SensitiveDataRedactor::redact([]));
    }

    public function test_accepts_custom_sensitive_keys(): void
    {
        $data = [
            'custom_field' => 'secret',
            'api_key' => 'should-stay',
        ];

        $result = SensitiveDataRedactor::redact($data, ['custom_field']);

        $this->assertEquals(SensitiveDataRedactor::REDACTED_VALUE, $result['custom_field']);
        $this->assertEquals('should-stay', $result['api_key']);
    }

    public function test_should_redact_key_case_insensitive(): void
    {
        $this->assertTrue(SensitiveDataRedactor::shouldRedactKey('PASSWORD'));
        $this->assertTrue(SensitiveDataRedactor::shouldRedactKey('Api_Key'));
        $this->assertFalse(SensitiveDataRedactor::shouldRedactKey('email'));
    }

    public function test_should_redact_key_contains_match(): void
    {
        $this->assertTrue(SensitiveDataRedactor::shouldRedactKey('my_secret_value'));
        $this->assertTrue(SensitiveDataRedactor::shouldRedactKey('database_password_hash'));
    }

    public function test_default_sensitive_keys_returns_array(): void
    {
        $keys = SensitiveDataRedactor::defaultSensitiveKeys();

        $this->assertNotEmpty($keys);
        $this->assertContains('password', $keys);
        $this->assertContains('api_key', $keys);
    }

    public function test_integer_keys_are_not_redacted(): void
    {
        $data = [
            0 => 'password=secret123',
            1 => 'normal_value',
        ];

        $result = SensitiveDataRedactor::redact($data);

        // Integer keys should not be treated as sensitive key names
        $this->assertEquals('password=secret123', $result[0]);
        $this->assertEquals('normal_value', $result[1]);
    }
}
