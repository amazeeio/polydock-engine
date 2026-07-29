<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Services\LagoonClientService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Token caching in LagoonClientService: successful tokens are reused for
 * ~2 minutes; SSH failures ('' return) are never cached; a bound
 * token_fetcher always short-circuits the cache.
 */
class LagoonClientServiceTokenCacheTest extends TestCase
{
    /** A config whose key file cannot exist, so the real SSH path fails fast. */
    private function unusableConfig(): array
    {
        return [
            'ssh_user' => 'lagoon',
            'ssh_server' => 'ssh.lagoon.test',
            'ssh_port' => '32222',
            'ssh_private_key_file' => '/nonexistent/key/file',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Guard against a leaked binding from other tests. offsetUnset drops
        // bind()/singleton() registrations too, which forgetInstance() would
        // leave in place.
        if (app()->bound('polydock.lagoon.token_fetcher')) {
            app()->offsetUnset('polydock.lagoon.token_fetcher');
        }
    }

    public function test_a_cached_token_is_returned_without_ssh(): void
    {
        $config = $this->unusableConfig();
        Cache::put(LagoonClientService::tokenCacheKey($config), 'tok-123', 60);

        $token = app(LagoonClientService::class)->getLagoonToken($config);

        $this->assertSame('tok-123', $token);
    }

    public function test_failed_fetches_are_never_cached(): void
    {
        $config = $this->unusableConfig();

        $token = app(LagoonClientService::class)->getLagoonToken($config);

        $this->assertSame('', $token);
        $this->assertNull(Cache::get(LagoonClientService::tokenCacheKey($config)));
    }

    public function test_a_bound_token_fetcher_bypasses_the_cache(): void
    {
        $config = $this->unusableConfig();
        Cache::put(LagoonClientService::tokenCacheKey($config), 'cached-tok', 60);
        app()->instance('polydock.lagoon.token_fetcher', fn (array $c) => 'bound-tok');

        try {
            $token = app(LagoonClientService::class)->getLagoonToken($config);
        } finally {
            app()->offsetUnset('polydock.lagoon.token_fetcher');
        }

        $this->assertSame('bound-tok', $token);
    }
}
