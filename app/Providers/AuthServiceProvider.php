<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\FakeOktaProvider;
use App\Models\PolydockAppInstance;
use App\Models\PolydockStore;
use App\Models\Role;
use App\Models\User;
use App\Models\UserGroup;
use App\Policies\ActivityPolicy;
use App\Policies\PolydockAppInstancePolicy;
use App\Policies\PolydockStorePolicy;
use App\Policies\RolePolicy;
use App\Policies\UserGroupPolicy;
use App\Policies\UserPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Okta\Provider as OktaProvider;
use Spatie\Activitylog\Models\Activity;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        PolydockStore::class => PolydockStorePolicy::class,
        UserGroup::class => UserGroupPolicy::class,
        PolydockAppInstance::class => PolydockAppInstancePolicy::class,
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Activity::class => ActivityPolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Gate::before(function (User $user) {
            if ($user->hasRole('super_admin')) {
                return true;
            }

            return null;
        });

        Event::listen(function (SocialiteWasCalled $event): void {
            // local/testing only: shared non-prod deployments (Lagoon dev)
            // must never accept the fake IdP, which grants roles from input.
            $useFake = config('okta.fake') && $this->app->environment('local', 'testing');

            $event->extendSocialite('okta', $useFake ? FakeOktaProvider::class : OktaProvider::class);
        });

        // Audit every interactive login with its provider (password or okta).
        Event::listen(function (Login $event): void {
            $provider = app()->bound('session') && session()->isStarted()
                ? session()->pull('auth_provider', 'password')
                : 'password';

            activity()
                ->causedBy($event->user instanceof Model ? $event->user : null)
                ->withProperties(['provider' => $provider, 'guard' => $event->guard])
                ->log('login');
        });
    }
}
