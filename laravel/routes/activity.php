<?php

use App\Http\Controllers\Activity\ActivityAuthController;
use App\Http\Controllers\Activity\ActivityCombleController;
use Illuminate\Support\Facades\Route;

/*
 * Discord Activity entry points — Comble played inside a Discord voice
 * channel via Discord's embedded-app iframe. Registered outside the `web`
 * middleware group the same way routes/discord.php is (see the `then:`
 * closure in bootstrap/app.php, which also applies the `discord.web`/
 * `discord.activity` feature flags and a shared throttle to this whole
 * file): no Laravel session survives Discord's discordsays.com iframe
 * proxy reliably, so these routes never depend on one for anything,
 * including CSRF. Identity instead rides a short-lived Bearer token minted
 * by ActivityAuthController and checked by the `activity.auth` middleware
 * (VerifyActivityToken) — see that controller's docblock for the full
 * handshake.
 *
 * Discord's Root URL Mapping is hostname-only (no path) and always loads
 * the mapped target's own "/" as the Activity's initial document — it has
 * no way to be told "load /activity/comble" on the main site. That's why a
 * second front-controller bridge exists at comble/index.php +
 * comble/.htaccess: a dedicated comble.* subdomain, pointed at by that
 * mapping, whose "/" needs to BE the Activity. When DISCORD_ACTIVITY_DOMAIN
 * is configured, these routes are domain-scoped to live at that subdomain's
 * root ("/", "/token", "/state", "/guess") instead of colliding with the
 * main site's own "/".
 *
 * Falls back to an "/activity/comble" prefix on the main domain, unscoped
 * by domain, when DISCORD_ACTIVITY_DOMAIN isn't set — e.g. local
 * development behind a single-origin tunnel (cloudflared/ngrok), where
 * there's no separate main site sharing that origin to collide with.
 *
 * Both branches register the exact same route names (activity.comble.*),
 * so nothing else in the app — SecurityHeaders' routeIs('activity.*') CSP
 * carve-out, the route() calls in the Activity's own views/controller —
 * needs to know or care which branch is active.
 */
$registerComble = function () {
    Route::get('/', [ActivityCombleController::class, 'show'])->name('show');
    Route::post('/token', [ActivityAuthController::class, 'exchange'])->name('token');

    Route::middleware('activity.auth')->group(function () {
        Route::get('/state', [ActivityCombleController::class, 'state'])->name('state');
        Route::post('/guess', [ActivityCombleController::class, 'guess'])->name('guess');
    });
};

$activityDomain = config('services.discord.activity_domain');

if ($activityDomain) {
    Route::domain($activityDomain)->name('activity.comble.')->group($registerComble);
} else {
    Route::prefix('activity/comble')->name('activity.comble.')->group($registerComble);
}
