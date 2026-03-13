<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class RouteWithToken
{
    /**
     * Handle an incoming request.
     *
     *
     * @throws UnauthorizedHttpException
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse|JsonResponse|BinaryFileResponse
    {
        $token = $request->bearerToken();

        if (empty(env('STATIC_BEARER_TOKEN'))) {
            throw new UnauthorizedHttpException('Bearer', 'Token not set on server. Please set STATIC_BEARER_TOKEN in .env file.');
        }

        if ($token !== env('STATIC_BEARER_TOKEN')) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid token');
        }

        return $next($request);
    }
}
