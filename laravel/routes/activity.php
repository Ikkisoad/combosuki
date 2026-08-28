<?php

use App\Http\Controllers\Activity\ActivityAuthController;
use App\Http\Controllers\Activity\ActivityCombleController;
use Illuminate\Support\Facades\Route;

/*
 * Discord Activity entry points — Comble played inside a Discord voice
 * channel via Discord's embedded-app iframe. Registered outside the `web`
 * middleware group the same way routes/discord.php is (see the `then:`
 * closure in bootstrap/app.php, which also applies the `discord.web`
 * feature flag and a shared throttle to this whole file): no Laravel
 * session survives Discord's discordsays.com iframe proxy reliably, so
 * these routes never depend on one for anything, including CSRF. Identity
 * instead rides a short-lived Bearer token minted by ActivityAuthController
 * and checked by the `activity.auth` middleware (VerifyActivityToken) — see
 * that controller's docblock for the full handshake.
 */
Route::prefix('activity')->name('activity.')->group(function () {
    Route::get('/comble', [ActivityCombleController::class, 'show'])->name('comble.show');
    Route::post('/comble/token', [ActivityAuthController::class, 'exchange'])->name('comble.token');

    Route::middleware('activity.auth')->group(function () {
        Route::get('/comble/state', [ActivityCombleController::class, 'state'])->name('comble.state');
        Route::post('/comble/guess', [ActivityCombleController::class, 'guess'])->name('comble.guess');
    });
});
