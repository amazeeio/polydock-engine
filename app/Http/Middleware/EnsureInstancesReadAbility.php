<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstancesReadAbility
{
    /**
     * Ensure a Sanctum token with instances.read ability is used.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user || ! $token) {
            return response()->json([
                'message' => 'API token is required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $token->can('instances.read') && ! $token->can('*')) {
            return response()->json([
                'message' => 'Token does not have the required instances.read ability.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
