<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * TOTP is enforced for password users only; Okta users authenticate
 * (and MFA) upstream at Okta, per Okta org sign-on policy.
 */
class EnsureMultiFactorAuthenticationIsEnabledForPasswordUsers extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Filament::auth()->user();

        // Exempt only when all three hold: the account's sole login path is
        // Okta (linked AND no password — a temporary runbook password
        // re-opens password login), and THIS session was stamped by the Okta
        // callback as MFA-verified upstream. Any other session — password
        // login, or an Okta session whose amr claim showed no MFA — gets
        // local TOTP.
        if (
            $user?->okta_sub !== null
            && $user->password === null
            && $request->session()->get('okta_mfa_verified') === true
        ) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
