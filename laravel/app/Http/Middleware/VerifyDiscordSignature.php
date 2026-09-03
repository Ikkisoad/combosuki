<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SodiumException;
use Symfony\Component\HttpFoundation\Response;

class VerifyDiscordSignature
{
    /**
     * How far X-Signature-Timestamp may be from our own clock, in seconds.
     * Matches the window Discord's own verification guidance suggests, and
     * leaves room for ordinary clock skew between us and Discord.
     */
    private const TIMESTAMP_TOLERANCE = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');
        $publicKey = config('services.discord.public_key');

        // ctype_digit before the cast below: (int) 'abc' is 0, which would
        // read as 1970 and sail through the freshness check as "very old"
        // only if that check were written as a one-sided comparison — and a
        // non-numeric header should be rejected outright regardless.
        abort_unless($signature && $timestamp && $publicKey && ctype_digit($timestamp), 401);

        // The signature covers timestamp . body, so neither can be altered —
        // but without this, a captured (body, timestamp, signature) triple
        // stays valid forever and can be replayed to re-run whatever command
        // it carried (/csk submit creates a real combo, and this endpoint is
        // outside the web group, so there is no session or CSRF behind it).
        // This bounds the replay window rather than closing it; eliminating
        // replay entirely would need a seen-nonce cache.
        abort_if(abs(time() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE, 401);

        try {
            $verified = sodium_crypto_sign_verify_detached(
                sodium_hex2bin($signature),
                $timestamp.$request->getContent(),
                sodium_hex2bin($publicKey)
            );
        } catch (SodiumException) {
            $verified = false;
        }

        abort_unless($verified, 401);

        return $next($request);
    }
}
