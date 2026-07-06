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

        // Exempt only users whose sole login path is Okta: linked AND no
        // password. A temporary runbook password re-opens password login,
        // so it must also re-enable the local TOTP requirement.
        if ($user?->okta_sub !== null && $user->password === null) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
