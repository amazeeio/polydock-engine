<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\PolydockEngine\Helpers\LagoonHelper;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Pins that the region lookup reads its endpoint from config (env() breaks
 * silently under `config:cache` in production).
 */
class LagoonHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_region_lookup_uses_the_configured_endpoint(): void
    {
        config([
            'polydock.service_providers_singletons.PolydockServiceProviderFTLagoon.endpoint' => 'https://lagoon.test/graphql',
            'polydock.lagoon_cores' => [
                'https://lagoon.test/graphql' => [
                    'lagoon_deploy_regions' => [
                        '7' => ['region_name' => 'Test Region', 'region_code' => 'test-1'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('Test Region', LagoonHelper::getLagoonCodeDataValueForRegion('7', 'region_name'));
    }

    public function test_unknown_endpoint_returns_null_without_throwing(): void
    {
        config([
            'polydock.service_providers_singletons.PolydockServiceProviderFTLagoon.endpoint' => 'https://not-in-map.test/graphql',
            'polydock.lagoon_cores' => [],
        ]);

        $this->assertNull(LagoonHelper::getLagoonCoreDataForRegion('7'));
    }
}
