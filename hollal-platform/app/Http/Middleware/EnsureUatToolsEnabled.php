<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UAT tools page is removed on production publish — abort when the flag is off.
 */
class EnsureUatToolsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('uat_tools.enabled'), 404);

        return $next($request);
    }
}
