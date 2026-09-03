<?php

use App\Http\Middleware\EnsureDiscordActivityEnabled;
use App\Http\Middleware\EnsureDiscordIntegrationEnabled;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsModerator;
use App\Http\Middleware\EnsureUserIsTrusted;
use App\Http\Middleware\GuardScalarQueryParameters;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyActivityToken;
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
            Route::middleware(['throttle:60,1', 'discord.web', 'discord.activity'])->group(base_path('routes/activity.php'));
            // Explicit `web` group, not the `web:` file passed to
            // withRouting() above — Comble needs the ordinary session/
            // cookie/CSRF stack (see routes/comble.php's docblock) but must
            // NOT be gated behind discord.web/discord.activity the way
            // routes/activity.php is, since it predates and is independent
            // of every Discord feature.
            Route::middleware('web')->group(base_path('routes/comble.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        // Runs before the router so no controller can be handed an array
        // where it reads a scalar search term — see the middleware's docblock
        // for the unauthenticated 500s that closes.
        $middleware->prepend(GuardScalarQueryParameters::class);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'trusted' => EnsureUserIsTrusted::class,
            'moderator' => EnsureUserIsModerator::class,
            'discord.web' => EnsureDiscordIntegrationEnabled::class,
            'discord.activity' => EnsureDiscordActivityEnabled::class,
            'activity.auth' => VerifyActivityToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('discord/*') || $request->is('activity/*') || $request->routeIs([
                'lists.entries.reassign',
                'lists.manage.pages.bulk',
                'lists.manage.categories.bulk',
                'lists.manage.canvas.combos.search',
                'lists.manage.canvas.nodes.store',
                'lists.manage.canvas.nodes.update',
                'lists.manage.canvas.nodes.destroy',
                'lists.manage.canvas.edges.store',
                'lists.manage.canvas.edges.update',
                'lists.manage.canvas.edges.destroy',
                'comble.guess',
                'comble.guess.date',
            ]),
        );

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            // Activity routes carry no Laravel session (see
            // routes/activity.php), so the session-based redirect below
            // would itself throw for a 403 on one of them — never reachable
            // today (no route in that file aborts 403), but this keeps a
            // future one from crashing instead of just returning JSON.
            if ($e->getStatusCode() !== 403 || $request->expectsJson() || $request->is('activity/*')) {
                return null;
            }

            return redirect()->to(url()->previous(route('home')))
                ->with('error', "You don't have permission to do that.");
        });
    })->create();
