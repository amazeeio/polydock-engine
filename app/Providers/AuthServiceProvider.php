<?php

namespace App\Providers;

use App\Models\PolydockStore;
use App\Policies\PolydockStorePolicy;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        PolydockStore::class => PolydockStorePolicy::class,
    ];
}
