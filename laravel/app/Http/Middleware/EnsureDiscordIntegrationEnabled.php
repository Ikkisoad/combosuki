<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every web-facing Discord OAuth route behind the admin flag.
 *
 * Aborts 404 rather than 403, following the matches_enabled precedent
 * (MatchController does `abort_unless($game->matches_enabled, 404)`): a
 * switched-off feature should look absent, not forbidden.
 *
 * Deliberately NOT applied to GET /account/connections — a user who already
 * linked an account still needs to see that the link exists while the
 * integration is off, so that page renders an unavailable notice instead.
 * The Discord bot (routes/discord.php) is out of scope for this flag.
 */
class EnsureDiscordIntegrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(SiteSetting::discordIntegrationEnabled(), 404);

        return $next($request);
    }
}
