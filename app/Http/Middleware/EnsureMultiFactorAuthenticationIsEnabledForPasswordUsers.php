<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/**
 * TOTP is enforced for password users only; Okta users authenticate
 * (and MFA) upstream at Okta and have no password.
 */
class EnsureMultiFactorAuthenticationIsEnabledForPasswordUsers extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (Filament::auth()->user()?->okta_sub !== null) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
