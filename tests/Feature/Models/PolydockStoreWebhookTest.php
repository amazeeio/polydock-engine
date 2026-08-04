<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\PolydockStoreWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PolydockStoreWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_https_urls_are_accepted(): void
    {
        $webhook = PolydockStoreWebhook::factory()->create([
            'url' => 'https://example.test/hook',
        ]);

        $this->assertTrue($webhook->exists);
    }

    public function test_plain_http_urls_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must use https://');

        PolydockStoreWebhook::factory()->create([
            'url' => 'http://example.test/hook',
        ]);
    }

    public function test_plain_http_is_rejected_on_update_too(): void
    {
        $webhook = PolydockStoreWebhook::factory()->create([
            'url' => 'https://example.test/hook',
        ]);

        $this->expectException(RuntimeException::class);

        $webhook->update(['url' => 'http://example.test/hook']);
    }

    public function test_localhost_http_urls_are_allowed_for_local_development(): void
    {
        foreach (['http://localhost:8080/hook', 'http://127.0.0.1/hook'] as $url) {
            $webhook = PolydockStoreWebhook::factory()->create(['url' => $url]);
            $this->assertTrue($webhook->exists);
        }

        // A non-loopback host merely containing "localhost" is not exempt.
        $this->assertFalse(PolydockStoreWebhook::isAllowedUrl('http://localhost.evil.test/hook'));
    }

    public function test_malformed_urls_are_rejected(): void
    {
        foreach (['https://', 'https:// example.test', 'not-a-url', 'ftp://example.test/hook', ''] as $url) {
            $this->assertFalse(PolydockStoreWebhook::isAllowedUrl($url), "Expected '{$url}' to be rejected");
        }
    }

    public function test_include_sensitive_data_defaults_to_false(): void
    {
        $webhook = PolydockStoreWebhook::factory()->create([
            'url' => 'https://example.test/hook',
        ]);

        $this->assertFalse($webhook->refresh()->include_sensitive_data);
    }
}
