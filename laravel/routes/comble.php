<?php

use App\Http\Controllers\CombleController;
use Illuminate\Support\Facades\Route;

/*
 * Comble (the daily "guess the combo" puzzle) — the web experience: full
 * page chrome, date picker, cookie-based per-day tracking, exactly as it's
 * always worked. Domain-scoped to DISCORD_ACTIVITY_DOMAIN when configured,
 * so it lives at that subdomain's root ("/") — Discord's Root URL Mapping
 * is hostname-only and always loads the mapped target's own "/" as the
 * Activity's initial document (see routes/activity.php's docblock), and
 * this is the one page that has to work equally well as a normal website
 * visit AND (via resources/js/comble.js's Discord-launch detection) as the
 * embedded Activity — so it has to be what's actually there.
 *
 * Falls back to a /comble prefix on the main domain, unscoped by domain,
 * when DISCORD_ACTIVITY_DOMAIN isn't set (e.g. local development). Comble
 * predates every Discord feature this app has and is independent of all of
 * them, which is why — unlike routes/activity.php — this isn't gated
 * behind the discord.web/discord.activity flags, and registers with the
 * ordinary `web` middleware group either way (see bootstrap/app.php): a
 * plain visit to this domain has none of the "no session survives
 * Discord's iframe proxy" problem routes/activity.php exists to route
 * around — only an actual Discord-embedded load does, and
 * resources/js/comble.js switches into that session-free flow itself once
 * it detects being framed (which, thanks to SecurityHeaders' CSP, can only
 * happen via Discord in the first place).
 *
 * Both branches register the exact same route names (comble.*), so nothing
 * else needs to know or care which is active — same convention as
 * routes/activity.php.
 */
$registerComble = function () {
    Route::get('/', [CombleController::class, 'show'])->name('show');
    Route::post('/guess', [CombleController::class, 'guess'])->middleware('throttle:20,1')->name('guess');
    Route::get('/{date}', [CombleController::class, 'show'])->where('date', '\d{4}-\d{2}-\d{2}')->name('show.date');
    Route::post('/{date}/guess', [CombleController::class, 'guess'])->where('date', '\d{4}-\d{2}-\d{2}')->middleware('throttle:20,1')->name('guess.date');
};

$activityDomain = config('services.discord.activity_domain');

if ($activityDomain) {
    Route::domain($activityDomain)->name('comble.')->group($registerComble);
} else {
    Route::prefix('comble')->name('comble.')->group($registerComble);
}
