<?php

namespace App\Http\Controllers;

use App\Models\Button;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\ListModel;
use App\Models\ResourceValue;
use App\Models\TierList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GameController extends Controller
{
    private const DEFAULT_BUTTONS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '214', '236', 'A', 'B', 'C', 'j', '>'];

    public function index(): View
    {
        $games = Game::orderBy('name')->get();

        return view('games.index', ['games' => $games]);
    }

    public function create(): View
    {
        return view('games.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'image' => ['required', 'string', 'max:255'],
        ]);

        $game = DB::transaction(function () use ($validated) {
            $game = Game::create([
                'name' => $validated['name'],
                'image' => $validated['image'],
                'complete' => 0,
                // Legacy per-game password gating was replaced by user auth
                // (see AnonymousWriteAccessTest); this column is only kept
                // NOT NULL by the schema, so it's never surfaced or checked.
                'modPass' => bcrypt(Str::random(32)),
            ]);

            foreach (self::DEFAULT_BUTTONS as $order => $name) {
                Button::create(['name' => $name, 'game_idgame' => $game->idgame, 'order' => $order]);
            }

            Character::create(['name' => 'Combo Chan', 'game_idgame' => $game->idgame]);

            $whereResource = GameResource::create([
                'game_idgame' => $game->idgame,
                'text_name' => 'Where?',
                'type' => 1,
                'primaryORsecundary' => 1,
            ]);
            ResourceValue::create(['value' => 'Midscreen', 'order' => 0, 'game_resources_idgame_resources' => $whereResource->idgame_resources]);
            ResourceValue::create(['value' => 'Corner', 'order' => 1, 'game_resources_idgame_resources' => $whereResource->idgame_resources]);

            GameEntry::create(['title' => 'Combo', 'gameid' => $game->idgame, 'order' => -1]);
            GameEntry::create(['title' => 'Okizeme', 'gameid' => $game->idgame]);
            GameEntry::create(['title' => 'Mix Up', 'gameid' => $game->idgame]);

            return $game;
        });

        return redirect()->route('admin.game.edit', $game)->with('status', 'Game created! Finish setting it up below.');
    }

    public function show(Game $game): View
    {
        $game->load(['links' => fn ($query) => $query->orderBy('Title')]);

        $characters = Character::where('game_idgame', $game->idgame)
            ->withCount('combos')
            ->orderByDesc('combos_count')
            ->orderBy('name')
            ->get();

        $latestCombos = Combo::with(['character', 'listingType', 'user'])
            ->whereHas('character', fn ($query) => $query->where('game_idgame', $game->idgame))
            ->orderByDesc('submited')
            ->limit(5)
            ->get();

        $listingTypes = GameEntry::where('gameid', $game->idgame)->orderBy('order')->orderBy('title')->get();

        $primaryResources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->with('values')
            ->orderBy('text_name')
            ->get();

        $guides = ListModel::where('game_idgame', $game->idgame)
            ->with('user')
            ->orderByDesc('idlist')
            ->limit(10)
            ->get();

        $tierLists = TierList::where('game_idgame', $game->idgame)
            ->with('user')
            ->orderByDesc('idtier_list')
            ->limit(10)
            ->get();

        return view('games.show', [
            'game' => $game,
            'characters' => $characters,
            'latestCombos' => $latestCombos,
            'listingTypes' => $listingTypes,
            'primaryResources' => $primaryResources,
            'guides' => $guides,
            'tierLists' => $tierLists,
        ]);
    }

}
