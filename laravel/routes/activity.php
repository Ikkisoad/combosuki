<?php

use App\Http\Controllers\Activity\ActivityAssetController;
use App\Http\Controllers\Activity\ActivityAuthController;
use App\Http\Controllers\Activity\ActivityCombleController;
use Illuminate\Support\Facades\Route;

/*
 * Discord Activity data endpoints for Comble — the code→token exchange and
 * the Bearer-token-protected state/guess JSON endpoints
 * resources/js/comble.js's bootDiscordActivity() calls once it detects the
 * page is framed (see that function's docblock in comble.js). The
 * Activity's actual *page* is comble.show (routes/comble.php,
 * CombleController) — Discord's Root URL Mapping is hostname-only and
 * always loads that domain's "/" as the initial document, so it has to be
 * what's actually there; these routes are deliberately NOT registered at
 * "/" themselves.
 *
 * Registered outside the `web` middleware group the same way
 * routes/discord.php is (see the `then:` closure in bootstrap/app.php,
 * which also applies the `discord.web`/`discord.activity` feature flags
 * and a shared throttle to this whole file): no Laravel session survives
 * Discord's discordsays.com iframe proxy reliably, so these routes never
 * depend on one for anything, including CSRF. Identity instead rides a
 * short-lived Bearer token minted by ActivityAuthController and checked by
 * the `activity.auth` middleware (VerifyActivityToken) — see that
 * controller's docblock for the full handshake.
 *
 * Domain-scoped to DISCORD_ACTIVITY_DOMAIN when configured, under an
 * `/activity` sub-prefix so they don't collide with comble.show's own
 * "/guess" (routes/comble.php, the ordinary cookie-based web guess) living
 * on the exact same domain. Falls back to an "/activity/comble" prefix on
 * the main domain otherwise (e.g. local development behind a single-origin
 * tunnel) — comble.show lives at a completely different "/comble" prefix
 * there, so no sub-prefix is needed to disambiguate them in that branch.
 */
$registerComble = function () {
    Route::post('/token', [ActivityAuthController::class, 'exchange'])->name('token');

    Route::middleware('activity.auth')->group(function () {
        Route::get('/state', [ActivityCombleController::class, 'state'])->name('state');
        Route::post('/guess', [ActivityCombleController::class, 'guess'])->name('guess');
    });
};

$activityDomain = config('services.discord.activity_domain');

if ($activityDomain) {
    Route::domain($activityDomain)->group(function () {
        // Apache's own static-file serving can't reach the sibling
        // laravel/public folder from this subdomain's sandboxed docroot
        // (see comble/.htaccess and ActivityAssetController) — needed here
        // for everything comble.show's full page chrome references
        // (build/ assets, img/ favicons and backgrounds), not just this
        // file's own Activity-specific data.
        Route::get('/build/{path}', [ActivityAssetController::class, 'show'])->where('path', '.*')->name('activity.build-asset');
        Route::get('/img/{path}', [ActivityAssetController::class, 'show'])->where('path', '.*')->name('activity.img-asset');
    });

    Route::domain($activityDomain)->prefix('activity')->name('activity.comble.')->group($registerComble);
} else {
    Route::prefix('activity/comble')->name('activity.comble.')->group($registerComble);
}
