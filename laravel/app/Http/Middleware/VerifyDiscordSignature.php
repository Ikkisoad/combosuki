<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SodiumException;
use Symfony\Component\HttpFoundation\Response;

class VerifyDiscordSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature-Ed25519');
        $timestamp = $request->header('X-Signature-Timestamp');
        $publicKey = config('services.discord.public_key');

        abort_unless($signature && $timestamp && $publicKey, 401);

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
