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
        // out. Discord's own client needs to be able to embed comble.show
        // (and the Activity's data endpoints) inside its iframe, so those
        // are carved out here instead; every other route keeps the strict
        // same-origin default.
        //
        // Two ways a request qualifies: its route name starts with
        // "activity." (covers the Activity's data endpoints in both the
        // domain-scoped and the local-dev prefix-fallback branch — see
        // routes/activity.php), or its Host is the dedicated comble.*
        // subdomain when one is configured (covers comble.show itself,
        // which isn't activity.*-named but lives on that same domain and
        // has to be embeddable too — see routes/comble.php). The host
        // check only ever matches in the domain-scoped branch: with no
        // subdomain configured, DISCORD_ACTIVITY_DOMAIN is empty and can
        // never equal a real request's Host.
        $activityDomain = config('services.discord.activity_domain');
        $isActivitySurface = $request->routeIs('activity.*')
            || ($activityDomain && $request->getHost() === $activityDomain);

        if ($isActivitySurface) {
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
