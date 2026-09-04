<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\ListModel;
use App\Services\ComboFlowChartBuilder;
use App\Services\ComboNotationRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only, fully public JSON API backing the Input Viewer's "Trials" tab:
 * search guides, list a guide's combos, and fetch one combo's move
 * breakdown to drive the live practice widget. No auth/ownership gate on
 * any of this — same openness as /lists/{list} and /combos/{combo}, both
 * already unauthenticated routes this reads from.
 */
class InputViewerTrialController extends Controller
{
    public function searchGuides(Request $request): JsonResponse
    {
        $query = ListModel::with('game')
            ->where('type', '!=', 0)
            ->where('is_favorite_guide', false);

        if ($request->filled('q')) {
            $query->where('list_name', 'like', '%'.$request->string('q').'%');
        }

        if ($request->filled('game_idgame')) {
            $query->where('game_idgame', $request->integer('game_idgame'));
        }

        // The Trials tab's guide picker is a game-scoped <select> (pick a
        // game, then pick from that game's guides), not a free-text search
        // — so once a game is chosen, list every guide for it alphabetically
        // rather than just the top few by views.
        $guides = $request->filled('game_idgame')
            ? $query->orderBy('list_name')->limit(200)->get()
            : $query->orderByDesc('views')->limit(20)->get();

        return response()->json([
            'guides' => $guides->map(fn (ListModel $list) => [
                'idlist' => $list->idlist,
                'list_name' => $list->list_name,
                'game_name' => $list->game?->name,
            ]),
        ]);
    }

    public function guideCombos(ListModel $list, Request $request): JsonResponse
    {
        $query = $list->combos()
            ->with(['character', 'listingType'])
            ->visibleTo(auth()->user());

        if ($request->filled('q')) {
            $q = $request->string('q')->toString();
            $query->where(function (Builder $builder) use ($q) {
                $builder->where('combo', 'like', "%{$q}%")
                    ->orWhereHas('character', fn (Builder $c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        $combos = $query->orderByDesc('damage')->limit(30)->get();

        $renderer = app(ComboNotationRenderer::class);

        return response()->json([
            'combos' => $combos->map(fn (Combo $combo) => [
                'idcombo' => $combo->idcombo,
                'character_name' => $combo->character->name,
                'damage' => $combo->damage,
                'type_title' => $combo->listingType?->title,
                'notation_html' => $renderer->render($combo->character->game, (string) $combo->combo),
            ]),
        ]);
    }

    public function comboMoves(Combo $combo, ComboFlowChartBuilder $builder): JsonResponse
    {
        $combo->loadMissing('character.game');

        return response()->json([
            'idcombo' => $combo->idcombo,
            'character_name' => $combo->character->name,
            'moves' => $builder->movesForCombo($combo),
        ]);
    }
}
