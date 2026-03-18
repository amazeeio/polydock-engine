<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\PolydockAppClassDiscovery;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Route;
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
        Gate::define('viewApiDocs', fn (?Authenticatable $user) => true);

        Scramble::configure()->expose(
            ui: '/api',
            document: '/api/openapi.json',
        )
        ->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->components->addSecurityScheme(
                'BearerAuth',
                SecurityScheme::http('bearer')
            );
        });

        Scramble::routes(fn (Route $route) => str_starts_with($route->uri(), 'api/'));
    }
}
