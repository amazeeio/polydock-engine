<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\BlockHorizonMutations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HorizonAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $guard = config('auth.defaults.guard');

        // Seed Horizon permissions
        $viewHorizon = Permission::findOrCreate('view_horizon', $guard);
        $mutateHorizon = Permission::findOrCreate('mutate_horizon', $guard);

        // Register default roles and assign permissions
        $superAdmin = Role::findOrCreate('super_admin', $guard);
        $superAdmin->givePermissionTo($viewHorizon);
        $superAdmin->givePermissionTo($mutateHorizon);

        $serviceAccount = Role::findOrCreate('service-account', $guard);
        $serviceAccount->givePermissionTo($viewHorizon);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guest_user_is_forbidden_from_horizon(): void
    {
        $response = $this->get('/horizon');
        $this->assertTrue($response->isForbidden() || $response->isRedirect());
    }

    public function test_regular_user_without_roles_is_forbidden_from_horizon(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/horizon');
        $response->assertForbidden();
    }

    public function test_super_admin_has_horizon_gate_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        // Can view Horizon index (GET)
        $this->actingAs($user)
            ->get('/horizon')
            ->assertOk();

        // Check viewHorizon gate directly
        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
    }

    public function test_service_account_has_horizon_gate_access(): void
    {
        $user = User::factory()->create();
        $user->assignRole('service-account');

        // Can view Horizon index (GET)
        $this->actingAs($user)
            ->get('/horizon')
            ->assertOk();

        // Check viewHorizon gate directly
        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
    }

    public function test_middleware_allows_get_requests_for_service_account(): void
    {
        $user = User::factory()->create();
        $user->assignRole('service-account');

        $middleware = new BlockHorizonMutations;
        $request = Request::create('/horizon/api/stats', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, function () {
            return response('Success');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function test_middleware_blocks_post_requests_for_service_account(): void
    {
        $user = User::factory()->create();
        $user->assignRole('service-account');

        $middleware = new BlockHorizonMutations;
        $request = Request::create('/horizon/api/jobs/failed/retry/1', 'POST');
        $request->setUserResolver(fn () => $user);

        try {
            $middleware->handle($request, function () {
                return response('Success');
            });
            $this->fail('Expected HttpException was not thrown.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals('You do not have permission to perform mutating actions on Horizon.', $e->getMessage());
        }
    }

    public function test_middleware_allows_post_requests_for_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $middleware = new BlockHorizonMutations;
        $request = Request::create('/horizon/api/jobs/failed/retry/1', 'POST');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, function () {
            return response('Success');
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }
}
