<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\PolydockAppClassDiscovery;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(PolydockAppClassDiscovery::class);

        Scramble::ignoreDefaultRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewApiDocs', fn (?object $user = null) => true);

        Scramble::configure()->expose(
            ui: '/api',
            document: '/openapi.json',
        );

        Scramble::routes(fn (\Illuminate\Routing\Route $route) => str_starts_with($route->uri(), 'api/'));
    }
}
