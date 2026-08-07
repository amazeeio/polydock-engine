<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The existing API suite only exercises correctly-scoped tokens. These pin the
 * refusal side of instances.read.ability / instances.write.ability — a token
 * scoped for reads must not reach a write route, and vice versa.
 */
class TokenAbilityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /** Param-free read route, so a pass through the middleware lands on 200. */
    private const string READ_ROUTE = '/api/enums';

    private const string WRITE_ROUTE = '/api/groups';

    public function test_read_token_cannot_reach_a_write_route(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['instances.read']);

        $this->postJson(self::WRITE_ROUTE, ['name' => 'nope'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Token does not have the required instances.write ability.');
    }

    public function test_write_token_cannot_reach_a_read_route(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['instances.write']);

        $this->getJson(self::READ_ROUTE)
            ->assertForbidden()
            ->assertJsonPath('message', 'Token does not have the required instances.read ability.');
    }

    public function test_token_without_abilities_is_refused_on_both_sides(): void
    {
        Sanctum::actingAs(User::factory()->create(), []);

        $this->getJson(self::READ_ROUTE)->assertForbidden();
        $this->postJson(self::WRITE_ROUTE, ['name' => 'nope'])->assertForbidden();
    }

    public function test_wildcard_token_passes_both_ability_checks(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->getJson(self::READ_ROUTE)->assertOk();

        // The write check lives in a separate middleware class, so it has to be
        // asserted independently — a wildcard regression there would not show
        // up on the read route. Only the ability gate is under test here, so
        // any non-403 means the middleware let the request through to the
        // controller's own validation.
        $this->postJson(self::WRITE_ROUTE, [])->assertStatus(422);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson(self::READ_ROUTE)->assertUnauthorized();
        $this->postJson(self::WRITE_ROUTE, ['name' => 'nope'])->assertUnauthorized();
    }

    public function test_session_authenticated_users_bypass_ability_scoping(): void
    {
        // Documented Sanctum behaviour, not a bug: a user resolved from the
        // session guard is given a TransientToken whose can() returns true for
        // everything, so ability scoping does not constrain cookie-authed
        // requests. Pinned because it is easy to assume otherwise when adding
        // a route — scoping is a token concern only.
        $this->actingAs(User::factory()->create());

        $this->getJson(self::READ_ROUTE)->assertOk();
        $this->postJson(self::WRITE_ROUTE, [])->assertStatus(422);
    }
}
