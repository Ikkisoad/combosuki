<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimelineController extends Controller
{
    private const PER_PAGE = 5;

    public function index(Request $request): View|JsonResponse
    {
        $combos = Combo::with(['character.game', 'listingType', 'user'])
            ->whereHas('character.game')
            ->orderByDesc('submited')
            ->paginate(self::PER_PAGE);

        if ($request->wantsJson()) {
            return response()->json([
                'html' => $combos->map(fn (Combo $combo) => view('timeline._combo', ['combo' => $combo])->render())->implode(''),
                'nextPageUrl' => $combos->nextPageUrl(),
            ]);
        }

        return view('timeline.index', ['combos' => $combos]);
    }
}
