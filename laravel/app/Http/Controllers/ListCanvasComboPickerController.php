<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Models\Combo;
use App\Models\GameResource;
use App\Models\ListModel;
use App\Models\ListPage;
use App\Services\ComboNotationRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListCanvasComboPickerController extends Controller
{
    use FiltersCombos;

    public function search(ListModel $list, ListPage $page, Request $request): JsonResponse
    {
        $this->authorize('update', $list);

        abort_if($page->idList !== $list->idlist || ! $page->isCanvas(), 404);

        if ($list->game_idgame === null) {
            return response()->json(['error' => 'This guide has no game set, so combos can\'t be searched.'], 422);
        }

        $game = $list->game;

        $primaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->with('values')
            ->get();

        $query = Combo::query()
            ->with(['character', 'listingType'])
            ->whereHas('character', fn (Builder $q) => $q->where('game_idgame', $game->idgame));

        if ($request->boolean('only_in_guide')) {
            $query->whereHas('lists', fn (Builder $q) => $q->where('list.idlist', $list->idlist));
        }

        $this->applyFilters($query, $request, $primaryResources, $game);
        $this->applyOrdering($query, $request);

        $combos = $query->limit(20)->get();

        $renderer = app(ComboNotationRenderer::class);

        return response()->json([
            'combos' => $combos->map(fn (Combo $combo) => [
                'idcombo' => $combo->idcombo,
                'character_name' => $combo->character->name,
                'damage' => $combo->damage,
                'type_title' => $combo->listingType?->title,
                'notation_html' => $renderer->render($game, $combo->combo),
            ]),
        ]);
    }
}
