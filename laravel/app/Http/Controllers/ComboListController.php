<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\ListModel;
use Illuminate\Http\JsonResponse;

class ComboListController extends Controller
{
    /**
     * Add a combo to one of the current user's editable lists, from the
     * "Add to list" dropdown on the combo page.
     */
    public function store(Combo $combo, ListModel $list): JsonResponse
    {
        $this->authorize('update', $list);

        $combo->loadMissing('character');

        if ($list->game_idgame !== null && $combo->character->game_idgame !== $list->game_idgame) {
            return response()->json(['error' => 'That combo does not belong to this list\'s game.'], 422);
        }

        $list->combos()->syncWithoutDetaching([
            $combo->idcombo => ['list_category_idlist_category' => null],
        ]);

        return response()->json(['status' => 'ok', 'list_id' => $list->idlist]);
    }
}
