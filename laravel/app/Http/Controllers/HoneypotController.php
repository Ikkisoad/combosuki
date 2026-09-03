<?php

namespace App\Http\Controllers;

use App\Models\BotHit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class HoneypotController extends Controller
{
    public function hit(Request $request): Response
    {
        // Read without casting: ?from[]=a makes this an array, and casting an
        // array to string emits an E_WARNING that Laravel's error handler
        // rethrows as ErrorException — a 500 with a full stack trace on a
        // public endpoint, which a bot could loop to flood the log.
        $from = $request->query('from');

        BotHit::create([
            'path' => Str::limit(is_string($from) ? $from : '', 255, ''),
            'ip_address' => (string) $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'created_at' => now(),
        ]);

        return response('', 204);
    }
}
