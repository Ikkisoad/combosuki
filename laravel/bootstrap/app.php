<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'trusted' => \App\Http\Middleware\EnsureUserIsTrusted::class,
            'moderator' => \App\Http\Middleware\EnsureUserIsModerator::class,
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
            ]),
        );

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 403 || $request->expectsJson()) {
                return null;
            }

            return redirect()->to(url()->previous(route('home')))
                ->with('error', "You don't have permission to do that.");
        });
    })->create();
