<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const string HOME = '/home';

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        parent::register();
    }

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function (): void {
            $datePattern = '^((?:19|20)\\d\\d)-(0?[1-9]|1[012])-([12][0-9]|3[01]|0?[1-9])';
            $yearMonthPattern = '^((?:19|20)\\d\\d)-(0[1-9]|1[012])';

            Route::pattern('dateFrom', $datePattern.'|beginning|start$');
            Route::pattern('dateTo', $datePattern.'|today|yesterday|end$');
            Route::pattern('date', $datePattern.'|today$');
            Route::pattern('yearMonth', $yearMonthPattern.'|current$');

            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip()));
    }
}
