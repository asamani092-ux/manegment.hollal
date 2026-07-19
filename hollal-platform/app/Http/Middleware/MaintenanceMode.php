<?php

namespace App\Http\Middleware;

use App\Support\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 11-B1 — platform maintenance mode, read live from platform_settings.
 *
 * Admins keep working while it is on, so it can be lifted from inside the app.
 */
class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) Setting::get('maintenance.enabled', false)) {
            return $next($request);
        }

        if ($request->user()?->can('settings.manage')) {
            return $next($request);
        }

        abort(503, (string) Setting::get('maintenance.message', 'المنصة تحت الصيانة مؤقتًا'));
    }
}
