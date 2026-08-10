<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A handful of response headers with no reasonable downside for a JSON API
 * with no browser-rendered HTML of its own (the SPA is a separate origin/
 * build, served by whatever sits in front of it — see deployment.md § 4).
 * HSTS is opt-in via APP_URL's scheme rather than unconditional, since it's
 * only meaningful (and only safe to send) once the app is actually served
 * over HTTPS — sending it over plain HTTP in local dev would do nothing but
 * confuses no one, so it's simplest to just gate it on the request itself.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
