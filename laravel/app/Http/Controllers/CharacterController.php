<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersCombos;
use App\Models\Character;
use App\Models\CharacterQuery;
use App\Models\Game;
use Illuminate\View\View;

class CharacterController extends Controller
{
    use FiltersCombos;

    public function show(Game $game, Character $character): View
    {
        $queries = CharacterQuery::where('game_idgame', $game->idgame)
            ->orderBy('order')
            ->orderBy('label')
            ->get();

        $topCombos = $queries->mapWithKeys(function (CharacterQuery $query) use ($game, $character) {
            $filters = array_merge($query->filters ?? [], ['characterid' => $character->idcharacter]);

            return [$query->idquery => $this->searchCombos($game, $filters, 1)->first()];
        });

        return view('characters.show', [
            'game' => $game,
            'character' => $character,
            'queries' => $queries,
            'topCombos' => $topCombos,
        ]);
    }
}
