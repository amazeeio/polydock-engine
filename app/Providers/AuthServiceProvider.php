<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\PolydockStore;
use App\Policies\PolydockStorePolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        PolydockStore::class => PolydockStorePolicy::class,
    ];
} 