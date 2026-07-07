<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnforcedMfaTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_password_user_without_mfa_is_redirected_to_setup(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
    }

    public function test_password_user_with_mfa_enabled_gets_through(): void
    {
        $user = $this->adminUser();
        $user->saveAppAuthenticationSecret('test-secret');

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    public function test_okta_user_with_mfa_verified_session_is_exempt_from_local_mfa(): void
    {
        $user = $this->adminUser();
        $user->forceFill(['okta_sub' => 'okta-sub-mfa', 'password' => null])->save();

        $this->actingAs($user)
            ->withSession(['okta_mfa_verified' => true])
            ->get('/admin')
            ->assertOk();
    }

    public function test_okta_user_without_the_session_stamp_is_not_exempt(): void
    {
        // Account shape alone is not enough: the session must have been
        // stamped by the Okta callback as upstream-MFA-verified.
        $user = $this->adminUser();
        $user->forceFill(['okta_sub' => 'okta-sub-mfa', 'password' => null])->save();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
    }

    public function test_okta_linked_user_with_a_password_is_not_exempt(): void
    {
        // A runbook-issued temporary password re-opens password login,
        // so local TOTP must be required again.
        $user = $this->adminUser();
        $user->forceFill(['okta_sub' => 'okta-sub-mfa'])->save();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
    }
}
