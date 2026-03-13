<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\PolydockAppClassDiscovery;
use Illuminate\Support\Facades\Route;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->booted(function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
