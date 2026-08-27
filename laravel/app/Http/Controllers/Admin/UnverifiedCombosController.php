<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\Game;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class UnverifiedCombosController extends Controller
{
    public function index(Game $game): View
    {
        $combos = $this->unverifiedCombosQuery($game)->paginate(25);

        return view('admin.unverified-combos.index', ['game' => $game, 'combos' => $combos]);
    }

    private function unverifiedCombosQuery(Game $game): Builder
    {
        return Combo::query()
            ->whereHas('character', fn (Builder $q) => $q->where('game_idgame', $game->idgame))
            ->whereNotNull('user_iduser')
            ->where(fn (Builder $q) => $q->whereNull('verified')->orWhere('verified', 0))
            ->with(['character', 'user'])
            ->orderByDesc('idcombo');
    }
}
