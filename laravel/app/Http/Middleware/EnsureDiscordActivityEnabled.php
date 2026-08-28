<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every Discord Activity route (routes/activity.php) behind its own
 * flag, independent of `discord.web` (EnsureDiscordIntegrationEnabled) —
 * both middleware run on the Activity's route group, so turning off either
 * one takes it down, but only this one is specific to the Activity. That
 * separation is the point: it lets the Activity be switched off on its own
 * (e.g. while its Discord Developer Portal URL Mapping isn't configured
 * correctly yet) without also locking out Discord sign-in/account-linking
 * on the website, which discord.web alone controls.
 *
 * Aborts 404 rather than 403, matching EnsureDiscordIntegrationEnabled's
 * precedent: a switched-off feature should look absent, not forbidden.
 */
class EnsureDiscordActivityEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(SiteSetting::discordActivityEnabled(), 404);

        return $next($request);
    }
}
