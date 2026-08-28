<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // This middleware is global, so it wraps the whole router — a
        // route-scoped middleware can't override what it sets here, since
        // it would run inside this one and get overwritten on the way back
        // out. The Discord Activity route needs to be embeddable inside
        // Discord's own iframe/proxy origins, so it's carved out by name
        // here instead; every other route keeps the strict same-origin
        // default.
        if ($request->routeIs('activity.*')) {
            $response->headers->set('Content-Security-Policy', 'frame-ancestors https://discord.com https://*.discord.com https://*.discordsays.com');
        } else {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        }

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
