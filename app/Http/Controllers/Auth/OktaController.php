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
use Spatie\SlackAlerts\Facades\SlackAlert;

class OktaController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless((bool) config('services.okta.client_id'), 404);

        return Socialite::driver('okta')->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless((bool) config('services.okta.client_id'), 404);

        $oktaUser = Socialite::driver('okta')->user();

        $sub = (string) $oktaUser->getId();
        $email = strtolower((string) $oktaUser->getEmail());
        abort_if($sub === '' || $email === '', 403, 'Okta login did not return a subject and email.');

        $user = User::where('okta_sub', $sub)->first()
            ?? $this->linkOrCreate($sub, $email, $oktaUser);

        session(['auth_provider' => 'okta']);
        Auth::login($user);
        session()->regenerate();

        return redirect()->intended('/admin');
    }

    private function linkOrCreate(string $sub, string $email, AbstractUser $oktaUser): User
    {
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
}
