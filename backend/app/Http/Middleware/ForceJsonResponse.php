<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces every API request to be treated as expecting JSON.
 *
 * Without this, a request that omits `Accept: application/json` (any raw curl
 * call, for instance) would hit Laravel's default HTML error rendering for
 * things like unhandled exceptions. This is the one-line fix that keeps every
 * response from this API — success or failure — JSON, with no per-exception
 * handling required.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
