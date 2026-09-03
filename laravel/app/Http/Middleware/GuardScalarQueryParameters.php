<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drops array-valued *query string* parameters so they never reach code that
 * assumes a scalar.
 *
 * Every search and filter endpoint in the app reads its query parameters with
 * $request->string($key) (there are ~18 such reads across the controllers),
 * and that helper hands the value to Str::of(), which throws on an array.
 * So a request as simple as ?combo[]=a, ?list_name[]=a or ?q[]=a turned a
 * public search page into an unhandled 500 with a stack trace in the log —
 * the same failure shape as the honeypot's ?from[]=a, and reachable without
 * authenticating.
 *
 * Fixing it at each read site would mean touching eight controllers and would
 * leave the next one to be written just as exposed, so it is handled here
 * instead — the same place, and the same shape, as Laravel's own TrimStrings
 * and ConvertEmptyStringsToNull global transforms.
 *
 * Deliberately query-only: request *bodies* legitimately carry arrays all
 * over this app (tier list entries, combo resources, bulk admin updates,
 * moderated game ids) and those are validated by their own rules. The query
 * string has exactly one legitimate array parameter, listed in $except.
 */
class GuardScalarQueryParameters
{
    /**
     * Query parameters that really are arrays and must be left alone.
     *
     * - path: the flow-chart walk, read as an array by
     *   CharacterController::flowChartNext/flowChartMatches.
     *
     * @var list<string>
     */
    protected array $except = [
        'path',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $query = $request->query->all();

        $cleaned = array_filter(
            $query,
            fn ($value, $key) => ! is_array($value) || in_array($key, $this->except, true),
            ARRAY_FILTER_USE_BOTH
        );

        if (count($cleaned) !== count($query)) {
            $request->query->replace($cleaned);

            // The merged input bag caches the combined query+body payload, so
            // it has to be rebuilt or $request->input() would keep handing
            // back the array this just removed.
            $request->setJson($request->json());
            $request->merge([]);
        }

        return $next($request);
    }
}
