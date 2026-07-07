<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Filament\Admin\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OktaLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.okta.base_url' => 'https://example.okta.com',
            'services.okta.client_id' => 'test-client-id',
            'services.okta.client_secret' => 'test-client-secret',
            'okta.domains' => ['amazee.io'],
        ]);
    }

    private function fakeOktaUser(string $sub, string $email, array $raw = [], ?array $idTokenClaims = null): void
    {
        $socialiteUser = (new SocialiteUser)
            ->setRaw(array_merge(['sub' => $sub, 'email' => $email, 'email_verified' => true], $raw))
            ->map([
                'id' => $sub,
                'email' => $email,
                'name' => $raw['name'] ?? 'Test User',
                'id_token' => $idTokenClaims === null ? null : $this->fakeIdToken($idTokenClaims),
            ]);

        Socialite::shouldReceive('driver')->with('okta')->andReturn(
            \Mockery::mock(AbstractProvider::class)
                ->shouldReceive('user')->andReturn($socialiteUser)->getMock()
        );
    }

    private function fakeIdToken(array $claims): string
    {
        return 'header.'.rtrim(strtr(base64_encode((string) json_encode($claims)), '+/', '-_'), '=').'.signature';
    }

    public function test_okta_routes_404_when_unconfigured(): void
    {
        config(['services.okta.client_id' => null]);

        $this->get('/auth/okta/redirect')->assertNotFound();
        $this->get('/auth/okta/callback')->assertNotFound();
    }

    public function test_redirect_sends_user_to_the_idp_with_state(): void
    {
        // In the test env the fake Okta driver is active (OKTA_FAKE in
        // phpunit.xml); the real provider's URL building is vendor code.
        $response = $this->get('/auth/okta/redirect');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/fake-okta/authorize?state=', $location);
    }

    public function test_callback_logs_in_existing_user_by_okta_sub(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['okta_sub' => 'okta-sub-1'])->save();

        $this->fakeOktaUser('okta-sub-1', 'different-email@amazee.io');

        $this->get('/auth/okta/callback')->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_links_existing_user_by_email_and_nulls_password(): void
    {
        $user = User::factory()->create([
            'email' => 'Staff@amazee.io',
            'password' => Hash::make('secret-password'),
        ]);

        $this->fakeOktaUser('okta-sub-2', 'staff@amazee.io');

        $this->get('/auth/okta/callback')->assertRedirect('/admin');

        $user->refresh();
        $this->assertSame('okta-sub-2', $user->okta_sub);
        $this->assertNull($user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_jit_creates_user_with_no_roles(): void
    {
        $this->fakeOktaUser('okta-sub-3', 'newstaff@amazee.io', [
            'given_name' => 'New',
            'family_name' => 'Staff',
        ]);

        $this->get('/auth/okta/callback')->assertRedirect('/admin');

        $user = User::where('email', 'newstaff@amazee.io')->firstOrFail();
        $this->assertSame('okta-sub-3', $user->okta_sub);
        $this->assertSame('New', $user->first_name);
        $this->assertSame('Staff', $user->last_name);
        $this->assertNull($user->password);
        $this->assertCount(0, $user->roles);
        $this->assertAuthenticatedAs($user);

        $this->assertTrue(
            Activity::where('description', 'User JIT-created from Okta login')->exists(),
        );
    }

    public function test_okta_login_is_audited_with_provider(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['okta_sub' => 'okta-sub-4'])->save();

        $this->fakeOktaUser('okta-sub-4', $user->email);

        $this->get('/auth/okta/callback');

        $login = Activity::where('description', 'login')->latest('id')->firstOrFail();
        $this->assertSame('okta', $login->properties['provider']);
    }

    public function test_login_page_redirects_okta_domain_to_okta(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['email' => 'staff@amazee.io'])
            ->call('authenticate')
            ->assertRedirect(route('okta.redirect'));
    }

    public function test_login_page_shows_password_step_for_other_domains(): void
    {
        $user = User::factory()->create([
            'email' => 'external@example.com',
            'password' => Hash::make('secret-password'),
        ]);
        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'external@example.com'])
            ->call('authenticate')
            ->assertSet('emailChecked', true)
            ->assertNoRedirect()
            ->fillForm(['password' => 'secret-password'])
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_refuses_to_link_or_create_when_email_unverified(): void
    {
        $existing = User::factory()->create([
            'email' => 'victim@amazee.io',
            'password' => Hash::make('secret-password'),
        ]);

        $this->fakeOktaUser('okta-sub-evil', 'victim@amazee.io', ['email_verified' => false]);

        $this->get('/auth/okta/callback')->assertForbidden();

        $existing->refresh();
        $this->assertNull($existing->okta_sub);
        $this->assertNotNull($existing->password);
        $this->assertGuest();
    }

    public function test_group_sync_is_skipped_when_groups_claim_is_missing(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['okta_sub' => 'okta-sub-8'])->save();
        Role::findOrCreate('support', 'web');
        $user->assignRole('support');

        // No 'groups' key in the raw payload at all (claim not configured).
        $this->fakeOktaUser('okta-sub-8', $user->email);

        $this->get('/auth/okta/callback');

        $this->assertTrue($user->fresh()->hasRole('support'));
    }

    public function test_callback_stamps_session_mfa_verified_when_amr_reports_mfa(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['okta_sub' => 'okta-sub-amr1'])->save();

        $this->fakeOktaUser('okta-sub-amr1', $user->email, [], ['amr' => ['mfa', 'otp', 'pwd']]);

        $this->get('/auth/okta/callback')->assertSessionHas('okta_mfa_verified', true);
    }

    public function test_callback_stamps_session_not_mfa_verified_when_amr_lacks_mfa(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['okta_sub' => 'okta-sub-amr2'])->save();

        $this->fakeOktaUser('okta-sub-amr2', $user->email, [], ['amr' => ['pwd']]);

        $this->get('/auth/okta/callback')->assertSessionHas('okta_mfa_verified', false);
    }

    public function test_callback_trusts_okta_policy_when_amr_claim_is_absent(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['okta_sub' => 'okta-sub-amr3'])->save();

        $this->fakeOktaUser('okta-sub-amr3', $user->email);

        $this->get('/auth/okta/callback')->assertSessionHas('okta_mfa_verified', true);
    }

    public function test_group_sync_grants_mapped_role(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['okta_sub' => 'okta-sub-5'])->save();

        $this->fakeOktaUser('okta-sub-5', $user->email, ['groups' => ['polydock-support']]);

        $this->get('/auth/okta/callback');

        $this->assertTrue($user->fresh()->hasRole('support'));
    }

    public function test_group_sync_revokes_mapped_role_when_group_absent(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['okta_sub' => 'okta-sub-6'])->save();
        Role::findOrCreate('support', 'web');
        $user->assignRole('support');

        $this->fakeOktaUser('okta-sub-6', $user->email, ['groups' => []]);

        $this->get('/auth/okta/callback');

        $this->assertFalse($user->fresh()->hasRole('support'));
    }

    public function test_group_sync_preserves_roles_outside_the_map(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['okta_sub' => 'okta-sub-7'])->save();
        Role::findOrCreate('service-account', 'web');
        $user->assignRole('service-account');

        $this->fakeOktaUser('okta-sub-7', $user->email, ['groups' => ['polydock-admins']]);

        $this->get('/auth/okta/callback');

        $user = $user->fresh();
        $this->assertTrue($user->hasRole('service-account'));
        $this->assertTrue($user->hasRole('super_admin'));
    }

    public function test_login_page_never_accepts_password_for_okta_domain(): void
    {
        User::factory()->create([
            'email' => 'sneaky@amazee.io',
            'password' => Hash::make('secret-password'),
        ]);

        // Even if the password step is somehow reached, an Okta-forced email
        // is redirected to Okta instead of being password-authenticated.
        Livewire::test(Login::class)
            ->set('emailChecked', true)
            ->fillForm(['email' => 'sneaky@amazee.io', 'password' => 'secret-password'])
            ->call('authenticate')
            ->assertRedirect(route('okta.redirect'));

        $this->assertGuest();
    }
}
