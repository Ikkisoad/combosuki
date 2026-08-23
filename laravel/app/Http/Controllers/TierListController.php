<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTierListRequest;
use App\Models\Game;
use App\Models\TierList;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TierListController extends Controller
{
    public function index(Request $request): View
    {
        $query = TierList::with(['game', 'user'])->orderByDesc('idtier_list');

        $game = null;

        if ($request->filled('game_idgame')) {
            $game = Game::find($request->integer('game_idgame'));
            $query->where('game_idgame', $request->integer('game_idgame'));
        }

        $tierLists = $query->paginate(30)->withQueryString();

        return view('tier-lists.index', ['tierLists' => $tierLists, 'game' => $game]);
    }

    public function create(): View
    {
        $games = Game::with(['characters' => function ($query) {
            $query->orderBy('name');
        }])->orderBy('name')->get();

        $catalog = $games->mapWithKeys(fn (Game $game) => [
            $game->idgame => $game->characters->map(fn ($character) => [
                'idcharacter' => $character->idcharacter,
                'name' => $character->name,
                'image' => $character->image ? Storage::url($character->image) : null,
            ]),
        ]);

        $users = auth()->user()?->is_admin
            ? User::where('iduser', '!=', auth()->id())->orderBy('nickname')->get(['iduser', 'nickname'])
            : collect();

        return view('tier-lists.create', ['games' => $games, 'catalog' => $catalog, 'users' => $users]);
    }

    public function store(StoreTierListRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $tierList = DB::transaction(function () use ($validated, $user) {
            $tierList = TierList::create([
                'title' => $validated['title'],
                'game_idgame' => $validated['game_idgame'],
                'user_iduser' => $user->is_admin && array_key_exists('user_iduser', $validated)
                    ? $validated['user_iduser']
                    : $user->iduser,
            ]);

            if ($user->is_admin && ! empty($validated['created_at'])) {
                $tierList->forceFill(['created_at' => $validated['created_at']])->save();
            }

            foreach ($validated['entries'] ?? [] as $order => $entry) {
                $tierList->entries()->create([
                    'character_idcharacter' => $entry['character_idcharacter'],
                    'tier' => $entry['tier'],
                    'order' => $order,
                ]);
            }

            return $tierList;
        });

        return redirect()->route('tier-lists.show', $tierList);
    }

    public function show(TierList $tierList): View
    {
        $tierList->load('game', 'user', 'entries.character');
        $tierList->increment('views');

        return view('tier-lists.show', ['tierList' => $tierList]);
    }
}
