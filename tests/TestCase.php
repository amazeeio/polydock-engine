<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Act as the given user with a confirmed and valid two-factor session,
     * so requests are not redirected by the enforced-2FA middleware.
     */
    protected function actingAsWithTwoFactor(User $user): static
    {
        $user->enableTwoFactorAuthentication();
        $user->breezySession->update(['two_factor_confirmed_at' => now()]);

        return $this->actingAs($user)
            ->withSession(['breezy_session_id' => md5((string) $user->breezySession->id)]);
    }
}
