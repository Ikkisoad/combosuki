<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMatchRequest;
use App\Http\Requests\UpdateMatchRequest;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MatchController extends Controller
{
    public function index(Game $game): View
    {
        abort_unless($game->matches_enabled, 404);

        $matches = GameMatch::where('game_idgame', $game->idgame)
            ->with(['playerOneCharacter', 'playerTwoCharacter', 'playerOneUser', 'playerTwoUser', 'user'])
            ->orderByDesc('played_at')
            ->paginate(20)
            ->withQueryString();

        return view('matches.index', [
            'game' => $game,
            'matches' => $matches,
        ]);
    }

    public function create(Game $game): View
    {
        abort_unless($game->matches_enabled, 404);

        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        return view('matches.create', [
            'game' => $game,
            'characters' => $characters,
        ]);
    }

    public function store(StoreMatchRequest $request, Game $game): RedirectResponse
    {
        abort_unless($game->matches_enabled, 404);

        $validated = $request->validated();

        GameMatch::create([
            'game_idgame' => $game->idgame,
            'player_one' => $validated['player_one'],
            'player_one_user_iduser' => $validated['player_one_user_iduser'] ?? null,
            'player_one_character_idcharacter' => $validated['player_one_character_idcharacter'],
            'player_two' => $validated['player_two'],
            'player_two_user_iduser' => $validated['player_two_user_iduser'] ?? null,
            'player_two_character_idcharacter' => $validated['player_two_character_idcharacter'],
            'video' => $validated['video'],
            'played_at' => $validated['played_at'],
            'user_iduser' => auth()->id(),
        ]);

        return redirect()->route('games.matches.index', $game)->with('status', 'Match submitted.');
    }

    public function edit(GameMatch $gameMatch): View
    {
        $this->authorize('update', $gameMatch);

        $gameMatch->load(['playerOneUser', 'playerTwoUser']);
        $game = $gameMatch->game;

        $characters = Character::where('game_idgame', $game->idgame)->orderBy('name')->get();

        return view('matches.edit', [
            'game' => $game,
            'match' => $gameMatch,
            'characters' => $characters,
        ]);
    }

    public function update(UpdateMatchRequest $request, GameMatch $gameMatch): RedirectResponse
    {
        $validated = $request->validated();

        $gameMatch->update([
            'player_one' => $validated['player_one'],
            'player_one_user_iduser' => $validated['player_one_user_iduser'] ?? null,
            'player_one_character_idcharacter' => $validated['player_one_character_idcharacter'],
            'player_two' => $validated['player_two'],
            'player_two_user_iduser' => $validated['player_two_user_iduser'] ?? null,
            'player_two_character_idcharacter' => $validated['player_two_character_idcharacter'],
            'video' => $validated['video'],
            'played_at' => $validated['played_at'],
        ]);

        return redirect()->route('games.matches.index', $gameMatch->game)->with('status', 'Match updated.');
    }

    public function destroy(GameMatch $gameMatch): RedirectResponse
    {
        $this->authorize('delete', $gameMatch);

        $game = $gameMatch->game;
        $gameMatch->delete();

        return redirect()->route('games.matches.index', $game)->with('status', 'Match deleted.');
    }
}
