<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Tests\TestCase;

class FakeOktaLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'okta.fake' => true,
            'services.okta.client_id' => 'fake',
            'services.okta.client_secret' => 'fake',
            'okta.domains' => ['amazee.io'],
        ]);
    }

    public function test_fake_form_is_404_when_fake_mode_disabled(): void
    {
        config(['okta.fake' => false]);

        $this->get('/fake-okta/authorize')->assertNotFound();
    }

    public function test_full_fake_okta_flow_logs_in_and_syncs_roles(): void
    {
        // Real redirect: stores OAuth state in the session, sends us to the fake form.
        $redirect = $this->get('/auth/okta/redirect');
        $redirect->assertRedirect();

        $location = (string) $redirect->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertNotEmpty($query['state']);

        $this->get($location)->assertOk()->assertSee('Fake Okta');

        // Submit the fake form straight to the real callback. The array session
        // driver doesn't persist between test requests and the cached driver
        // holds the first request object, so re-inject state and reset drivers.
        Socialite::forgetDrivers();
        $this->withSession(['state' => $query['state']])
            ->get('/auth/okta/callback?'.http_build_query([
                'state' => $query['state'],
                'email' => 'fake.staff@amazee.io',
                'given_name' => 'Fake',
                'family_name' => 'Staffer',
                'groups' => ['polydock-support'],
            ]))->assertRedirect('/admin');

        $user = User::where('email', 'fake.staff@amazee.io')->firstOrFail();
        $this->assertSame('fake-okta|fake.staff@amazee.io', $user->okta_sub);
        $this->assertTrue($user->hasRole('support'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_fake_callback_rejects_bad_state(): void
    {
        $this->expectException(InvalidStateException::class);

        $this->withoutExceptionHandling()
            ->withSession(['state' => 'the-real-state'])
            ->get('/auth/okta/callback?'.http_build_query([
                'state' => 'wrong-state',
                'email' => 'fake.staff@amazee.io',
            ]));
    }
}
