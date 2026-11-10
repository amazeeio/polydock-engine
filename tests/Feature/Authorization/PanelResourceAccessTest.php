<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Filament\Admin\Resources\ApiTokenResource;
use App\Filament\Admin\Resources\PolydockHostedFormResource;
use App\Filament\Admin\Resources\PolydockStoreAppResource;
use App\Filament\Admin\Resources\PolydockStoreResource;
use App\Filament\Admin\Resources\PolydockStoreWebhookCallResource;
use App\Filament\Admin\Resources\PolydockStoreWebhookResource;
use App\Filament\Admin\Resources\UserRemoteRegistrationResource;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SuperAdminRoleSeeder;
use Database\Seeders\SupportRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The support role may work with instances, users and groups only. Filament
 * allows anything without a policy, so these resources need an explicit deny.
 */
class PanelResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $support;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SuperAdminRoleSeeder::class, SupportRoleSeeder::class]);

        $this->support = User::factory()->create();
        $this->support->assignRole('support');
        $this->support->saveAppAuthenticationSecret('test-secret');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    #[DataProvider('superAdminOnlyResources')]
    public function test_support_cannot_reach_super_admin_only_resources(string $resource): void
    {
        $this->actingAs($this->support);

        self::assertFalse($resource::canAccess());
        self::assertFalse($resource::canCreate());
        self::assertSame(403, $this->get($resource::getUrl())->baseResponse->getStatusCode());
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resource
     */
    #[DataProvider('superAdminOnlyResources')]
    public function test_super_admin_still_reaches_them(string $resource): void
    {
        $this->actingAs($this->admin);

        self::assertTrue($resource::canAccess());
        Livewire::test($resource::getPages()['index']->getPage())->assertSuccessful();
    }

    /**
     * @return array<string, array{class-string<\Filament\Resources\Resource>}>
     */
    public static function superAdminOnlyResources(): array
    {
        return [
            'api tokens' => [ApiTokenResource::class],
            'stores' => [PolydockStoreResource::class],
            'store apps' => [PolydockStoreAppResource::class],
            'webhooks' => [PolydockStoreWebhookResource::class],
            'webhook calls' => [PolydockStoreWebhookCallResource::class],
            'hosted forms' => [PolydockHostedFormResource::class],
            'remote registrations' => [UserRemoteRegistrationResource::class],
        ];
    }

    public function test_support_cannot_grant_itself_super_admin_through_the_user_form(): void
    {
        $this->actingAs($this->support);

        Livewire::test(EditUser::class, ['record' => $this->support->getRouteKey()])
            ->assertFormFieldHidden('roles')
            ->set('data.roles', [Role::findByName('super_admin')->id])
            ->call('save');

        self::assertFalse($this->support->fresh()?->hasRole('super_admin'));
    }

    public function test_support_cannot_edit_or_delete_a_super_admin(): void
    {
        self::assertFalse($this->support->can('update', $this->admin));
        self::assertFalse($this->support->can('delete', $this->admin));

        $other = User::factory()->create();
        self::assertTrue($this->support->can('update', $other));
        self::assertTrue($this->support->can('update', $this->support));
    }

    public function test_super_admin_can_assign_roles_through_the_user_form(): void
    {
        $this->actingAs($this->admin);
        $target = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormFieldVisible('roles')
            ->fillForm(['roles' => [Role::findByName('support')->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        self::assertTrue($target->fresh()?->hasRole('support'));
    }

    public function test_bulk_delete_skips_super_admins_for_non_super_admins(): void
    {
        $this->support->givePermissionTo(Permission::findOrCreate('delete_user', 'web'));
        $this->actingAs($this->support);
        $other = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->callTableBulkAction('delete', [$this->admin, $other]);

        self::assertNotNull($this->admin->fresh());
        self::assertNull($other->fresh());
    }
}
