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
        BotHit::create([
            'path' => Str::limit((string) $request->query('from'), 255, ''),
            'ip_address' => (string) $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'created_at' => now(),
        ]);

        return response('', 204);
    }
}
