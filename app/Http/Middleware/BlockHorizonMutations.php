<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockHorizonMutations
{
    /**
     * Prevent users without mutate_horizon permission from mutating queues.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If the user does not have mutate_horizon permission and the request is a write/mutation request (not GET or HEAD)
        if ($user && ! $user->can('mutate_horizon') && ! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            abort(Response::HTTP_FORBIDDEN, 'You do not have permission to perform mutating actions on Horizon.');
        }

        return $next($request);
    }
}
