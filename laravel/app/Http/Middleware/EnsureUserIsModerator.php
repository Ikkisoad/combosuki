<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsModerator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isModerator(), 403);

        return $next($request);
    }
}
