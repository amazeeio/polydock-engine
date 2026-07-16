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
 * Smoke test for the OpenAPI contract-testing setup: exports the engine's
 * Scramble-generated spec and validates a live endpoint against it via
 * Spectator. If this passes, per-endpoint contract tests (including ones
 * against vendored consumed-API specs like api.amazee.ai) just need
 * Spectator::using('<spec>.json') and assertValidRequest/assertValidResponse.
 *
 * Note: endpoints documented with Scribe-style `@response` docblocks (e.g.
 * /regions) produce broken Scramble schemas — migrate those docblocks before
 * adding contract tests for them.
 */
class OpenApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_apps_endpoint_matches_the_generated_openapi_spec(): void
    {
        $specPath = base_path('tests/fixtures/openapi/polydock-engine.json');

        // Regenerate from current code so the contract can never go stale.
        Artisan::call('scramble:export', ['--path' => $specPath]);
        $this->assertFileExists($specPath);

        Spectator::using('polydock-engine.json');

        Sanctum::actingAs(User::factory()->create(), ['instances.read']);

        $this->getJson('/api/store-apps')
            ->assertValidRequest()
            ->assertValidResponse(200);
    }
}
