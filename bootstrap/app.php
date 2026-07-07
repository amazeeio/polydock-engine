<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureInstancesReadAbility;
use App\Http\Middleware\EnsureInstancesWriteAbility;
use App\Http\Middleware\XRobotsTagNoIndex;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $trustedProxies = env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(at: $trustedProxies);

        $middleware->append(XRobotsTagNoIndex::class);

        $middleware->alias([
            'instances.read.ability' => EnsureInstancesReadAbility::class,
            'instances.write.ability' => EnsureInstancesWriteAbility::class,
        ]);

        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return response('Unauthenticated.', 401);
        });
    })->create();
