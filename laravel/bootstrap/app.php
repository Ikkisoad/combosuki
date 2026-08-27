<?php

use App\Http\Middleware\EnsureDiscordIntegrationEnabled;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsModerator;
use App\Http\Middleware\EnsureUserIsTrusted;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('throttle:60,1')->group(base_path('routes/discord.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'trusted' => EnsureUserIsTrusted::class,
            'moderator' => EnsureUserIsModerator::class,
            'discord.web' => EnsureDiscordIntegrationEnabled::class,
        ]);
        // Comble's "starter" guess is compared character-for-character
        // (including spaces) against the combo's raw notation; the global
        // TrimStrings middleware would otherwise silently strip a leading or
        // trailing space the player legitimately typed as part of a
        // 6-character guess (e.g. a 5-character opening move followed by
        // the space before the next one).
        $middleware->trimStrings(except: ['starter']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('discord/*') || $request->routeIs([
                'lists.entries.reassign',
                'lists.manage.pages.bulk',
                'lists.manage.categories.bulk',
                'comble.guess',
                'comble.guess.date',
            ]),
        );

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 403 || $request->expectsJson()) {
                return null;
            }

            return redirect()->to(url()->previous(route('home')))
                ->with('error', "You don't have permission to do that.");
        });
    })->create();
