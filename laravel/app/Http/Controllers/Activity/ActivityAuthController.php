<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Exchanges the OAuth `code` the Discord Activity SDK's authorize() command
 * returns for a verified Discord user id, then mints a short-lived signed
 * token the Activity's subsequent requests present as a Bearer token.
 *
 * Deliberately doesn't use Socialite (as the regular web sign-in flow does,
 * see DiscordAuthController): Socialite's driver expects a redirect_uri
 * matching the app's own callback route, but a Discord Activity's embedded
 * OAuth flow exchanges its code with no redirect_uri at all — the whole
 * authorize hop happens inside the Discord client, never touching this
 * app's routes.
 *
 * No Laravel session is available inside a Discord Activity's iframe (see
 * routes/activity.php), so identity can't be carried the way the rest of
 * the site carries it. Instead: this exchanges the code once, reads the
 * verified Discord user id straight from Discord's own /users/@me (never
 * trusting a client-supplied id), and hands back a short-lived Crypt-sealed
 * token carrying just that id and an expiry. The Activity holds it in
 * memory and sends it back as `Authorization: Bearer <token>` on every
 * subsequent call — see VerifyActivityToken. The raw Discord access token
 * is also returned so the client can complete the SDK's own
 * `authenticate()` step (needed for the Activity to be fully recognized by
 * Discord's client, e.g. showing up to other participants) — this app
 * never stores that token or uses it for anything itself.
 */
class ActivityAuthController extends Controller
{
    private const TOKEN_TTL_HOURS = 2;

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string']]);

        $clientId = config('services.discord.client_id');
        $clientSecret = config('services.discord.client_secret');

        abort_if(! $clientId || ! $clientSecret, 500, 'Discord Activity integration is not configured.');

        try {
            $tokenResponse = Http::asForm()->timeout(5)->post('https://discord.com/api/v10/oauth2/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $validated['code'],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => "Couldn't reach Discord. Please try again."], 502);
        }

        if (! $tokenResponse->successful()) {
            return response()->json(['error' => "Couldn't complete Discord sign-in. Please try again."], 401);
        }

        $accessToken = $tokenResponse->json('access_token');

        try {
            $userResponse = Http::withToken($accessToken)->timeout(5)->get('https://discord.com/api/v10/users/@me');
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => "Couldn't reach Discord. Please try again."], 502);
        }

        if (! $userResponse->successful()) {
            return response()->json(['error' => "Couldn't verify your Discord account. Please try again."], 401);
        }

        $discordId = (string) $userResponse->json('id');

        if ($discordId === '' || ! ctype_digit($discordId)) {
            Log::warning('Discord Activity token exchange returned a malformed user id.');

            return response()->json(['error' => "Couldn't verify your Discord account. Please try again."], 401);
        }

        $expiresAt = now()->addHours(self::TOKEN_TTL_HOURS)->timestamp;

        return response()->json([
            'token' => Crypt::encryptString(json_encode(['uid' => $discordId, 'exp' => $expiresAt])),
            'access_token' => $accessToken,
            'username' => $userResponse->json('global_name') ?? $userResponse->json('username'),
        ]);
    }
}
