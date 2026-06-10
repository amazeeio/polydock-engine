<?php

declare(strict_types=1);

namespace App\Providers;

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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
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
    }
}
