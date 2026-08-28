<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the short-lived Bearer token ActivityAuthController mints after
 * exchanging a Discord Activity's OAuth code and confirming the resulting
 * Discord user id via /users/@me. Activity routes carry no Laravel session
 * (see routes/activity.php), so this token is the only proof of identity
 * their requests carry — sets the verified Discord user id as a request
 * attribute for the controller to read.
 */
class VerifyActivityToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        abort_if($token === null, 401, 'Missing Activity token.');

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException) {
            abort(401, 'Invalid Activity token.');
        }

        $userId = $payload['uid'] ?? null;
        $expiresAt = $payload['exp'] ?? null;

        if (! is_string($userId) || $userId === '' || ! is_int($expiresAt) || $expiresAt < now()->timestamp) {
            abort(401, 'Invalid or expired Activity token.');
        }

        $request->attributes->set('discord_user_id', $userId);

        return $next($request);
    }
}
