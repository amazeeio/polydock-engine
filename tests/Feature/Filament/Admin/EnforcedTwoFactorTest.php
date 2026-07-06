<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnforcedTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_user_without_two_factor_is_redirected_to_profile_setup(): void
    {
        $user = $this->adminUser();

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('filament.admin.pages.my-profile'));
    }

    public function test_user_with_confirmed_two_factor_but_no_session_is_challenged(): void
    {
        $user = $this->adminUser();
        $user->enableTwoFactorAuthentication();
        $user->breezySession->update(['two_factor_confirmed_at' => now()]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('filament.admin.auth.two-factor', ['next' => '/admin']));
    }

    public function test_user_with_confirmed_two_factor_and_valid_session_gets_through(): void
    {
        $user = $this->adminUser();
        $user->enableTwoFactorAuthentication();
        $user->breezySession->update(['two_factor_confirmed_at' => now()]);

        $this->actingAs($user)
            ->withSession(['breezy_session_id' => md5((string) $user->breezySession->id)])
            ->get('/admin')
            ->assertOk();
    }

    public function test_recovery_code_is_consumed_on_use(): void
    {
        $user = $this->adminUser();
        $user->enableTwoFactorAuthentication();

        $codes = $user->two_factor_recovery_codes;
        $this->assertCount(8, $codes);

        $user->destroyRecoveryCode($codes[0]);
        $user->refresh();

        $this->assertCount(7, $user->two_factor_recovery_codes);
        $this->assertNotContains($codes[0], $user->two_factor_recovery_codes);
    }
}
