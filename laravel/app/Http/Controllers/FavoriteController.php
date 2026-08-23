<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller
{
    /**
     * Add a combo to the current user's favorites guide, creating that guide
     * (with no game attached) the first time it's needed.
     */
    public function store(Combo $combo): JsonResponse
    {
        $guide = auth()->user()->getOrCreateFavoriteGuide();

        $guide->combos()->syncWithoutDetaching([
            $combo->idcombo => ['list_category_idlist_category' => null],
        ]);

        return response()->json(['status' => 'ok', 'list_id' => $guide->idlist]);
    }

    public function destroy(Combo $combo): JsonResponse
    {
        auth()->user()->favoriteGuide?->combos()->detach($combo->idcombo);

        return response()->json(['status' => 'ok']);
    }
}
