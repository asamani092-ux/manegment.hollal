<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets baseline HTTP security headers on every response.
 */
class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $response->headers->set(
            'Content-Security-Policy-Report-Only',
            "default-src 'self'; ".
            "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; ".
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; ".
            "img-src 'self' data:; ".
            "font-src 'self' https://cdnjs.cloudflare.com; ".
            "connect-src 'self'; ".
            "frame-ancestors 'none'"
        );

        return $response;
    }
}
