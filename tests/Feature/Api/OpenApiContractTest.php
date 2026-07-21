<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Spectator\Spectator;
use Tests\TestCase;

/**
 * Smoke tests for the OpenAPI contract-testing setup: exports the engine's
 * Scramble-generated spec and validates live endpoints against it via
 * Spectator. If these pass, per-endpoint contract tests (including ones
 * against vendored consumed-API specs like api.amazee.ai) just need
 * Spectator::using('<spec>.json') and assertValidRequest/assertValidResponse.
 *
 * Note: Scribe-style `@response <status> {...}` docblocks break Scramble's
 * schema inference (the status number is parsed as a literal body type) —
 * don't reintroduce them; Scramble infers responses from the code.
 */
class OpenApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Regenerate from current code so the contract can never go stale.
        Artisan::call('scramble:export', [
            '--path' => base_path('tests/fixtures/openapi/polydock-engine.json'),
        ]);

        Spectator::using('polydock-engine.json');
    }

    public function test_store_apps_endpoint_matches_the_generated_openapi_spec(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['instances.read']);

        $this->getJson('/api/store-apps')
            ->assertValidRequest()
            ->assertValidResponse(200);
    }

    public function test_regions_endpoint_matches_the_generated_openapi_spec(): void
    {
        $this->getJson('/api/regions')
            ->assertValidRequest()
            ->assertValidResponse(200);
    }
}
