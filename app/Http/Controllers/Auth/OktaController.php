<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Spatie\SlackAlerts\Facades\SlackAlert;

class OktaController extends Controller
{
    public function redirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        abort_unless((bool) config('services.okta.client_id'), 404);

        return Socialite::driver('okta')->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless((bool) config('services.okta.client_id'), 404);

        /** @var AbstractUser $oktaUser */
        $oktaUser = Socialite::driver('okta')->user();

        $sub = (string) $oktaUser->getId();
        $email = strtolower((string) $oktaUser->getEmail());
        abort_if($sub === '' || $email === '', 403, 'Okta login did not return a subject and email.');

        $user = User::where('okta_sub', $sub)->first()
            ?? $this->linkOrCreate($sub, $email, $oktaUser);

        // A missing groups claim (e.g. not yet configured in Okta) must not
        // revoke roles; only sync when the claim is present, even if empty.
        $raw = (array) $oktaUser->getRaw();
        if (array_key_exists('groups', $raw)) {
            $this->syncMappedRoles($user, (array) $raw['groups']);
        }

        session(['auth_provider' => 'okta']);
        Auth::login($user);
        session()->regenerate();

        // Session-level proof for the MFA-exemption middleware: this session
        // came from an Okta login, and Okta reported MFA where the amr claim
        // is available. Set after regenerate() so it lives in the final session.
        session(['okta_mfa_verified' => $this->upstreamMfaVerified($oktaUser)]);

        return redirect()->intended('/admin');
    }

    private function linkOrCreate(string $sub, string $email, AbstractUser $oktaUser): User
    {
        // Linking or creating an account by email requires Okta to vouch for
        // the address, or a hijacked/mistyped Okta email could bind to (and
        // take over) someone else's local account.
        abort_unless(
            ($oktaUser->getRaw()['email_verified'] ?? false) === true,
            403,
            'Okta email is not verified.',
        );

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            // Lazy account linking: no password path remains for Okta users.
            $user->forceFill(['okta_sub' => $sub, 'password' => null])->save();
            activity()->performedOn($user)->log('Okta account linked to existing user');

            return $user;
        }

        $raw = $oktaUser->getRaw();
        $user = new User([
            'first_name' => $raw['given_name'] ?? Str::before((string) $oktaUser->getName(), ' '),
            'last_name' => $raw['family_name'] ?? Str::after((string) $oktaUser->getName(), ' '),
            'email' => $email,
        ]);
        // JIT-created with zero roles: canAccessPanel denies until a role is granted.
        $user->forceFill(['okta_sub' => $sub, 'email_verified_at' => now()])->save();

        activity()->performedOn($user)->log('User JIT-created from Okta login');

        if (config('slack-alerts.webhook_urls.default')) {
            SlackAlert::message("Polydock: user JIT-created from Okta login: {$email} (no roles granted)");
        }

        return $user;
    }

    /**
     * Whether the Okta session behind this login satisfied MFA upstream.
     *
     * Okta includes "mfa" in the ID token's amr claim whenever MFA was
     * performed. When the claim is present without "mfa", the session is
     * not MFA-verified and the user falls back to local TOTP. When the
     * claim (or ID token) is absent, we trust the Okta org sign-on policy
     * to enforce MFA, per the agreed plan.
     */
    private function upstreamMfaVerified(AbstractUser $oktaUser): bool
    {
        $idToken = $oktaUser->id_token ?? null;

        if (! is_string($idToken) || substr_count($idToken, '.') !== 2) {
            return true;
        }

        // No signature verification needed: the token came straight from
        // Okta's token endpoint over TLS with client authentication.
        $claims = json_decode(base64_decode(strtr(explode('.', $idToken)[1], '-_', '+/')) ?: '', true);

        if (! is_array($claims) || ! array_key_exists('amr', $claims)) {
            return true;
        }

        return in_array('mfa', (array) $claims['amr'], true);
    }

    /**
     * Sync roles in okta.group_role_map from the token's groups claim:
     * grant when the group is present, revoke when absent. Roles outside
     * the map (e.g. manually granted) are never touched.
     */
    private function syncMappedRoles(User $user, array $groups): void
    {
        foreach (config('okta.group_role_map', []) as $group => $roleName) {
            $inGroup = in_array($group, $groups, true);

            if ($inGroup && ! $user->hasRole($roleName)) {
                $user->assignRole(Role::findOrCreate($roleName, 'web'));
                activity()
                    ->performedOn($user)
                    ->withProperties(['role' => $roleName, 'okta_group' => $group])
                    ->log('Role granted from Okta group sync');
            } elseif (! $inGroup && $user->hasRole($roleName)) {
                $user->removeRole($roleName);
                activity()
                    ->performedOn($user)
                    ->withProperties(['role' => $roleName, 'okta_group' => $group])
                    ->log('Role revoked from Okta group sync');
            }
        }
    }
}
