<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTierListRequest;
use App\Models\Game;
use App\Models\GamePatch;
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

        if ($request->filled('author')) {
            $author = '%'.$request->string('author').'%';
            $query->whereHas('user', fn ($q) => $q->where('nickname', 'like', $author));
        }

        $tierPatch = $request->input('tier_patch');

        if ($game && $tierPatch && $tierPatch !== 'all') {
            $patch = GamePatch::where('game_idgame', $game->idgame)->find($tierPatch);

            if ($patch) {
                $query->whereDate('created_at', '>=', $patch->released_at);

                if ($patch->ended_at) {
                    $query->whereDate('created_at', '<', $patch->ended_at);
                }
            }
        } else {
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->string('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->string('date_to'));
            }
        }

        $tierLists = $query->paginate(30)->withQueryString();

        $games = Game::orderBy('name')->get(['idgame', 'name']);

        return view('tier-lists.index', ['tierLists' => $tierLists, 'game' => $game, 'games' => $games]);
    }

    public function create(): View
    {
        $games = Game::with([
            'characters' => function ($query) {
                $query->orderBy('name');
            },
            'tierListResource.values' => function ($query) {
                $query->orderBy('order')->orderBy('value');
            },
            'tierListResource.values.characterAliases',
        ])->orderBy('name')->get();

        $catalog = $games->mapWithKeys(fn (Game $game) => [
            $game->idgame => [
                'characters' => $game->characters->map(fn ($character) => [
                    'idcharacter' => $character->idcharacter,
                    'name' => $character->name,
                    'image' => $character->image ? Storage::url($character->image) : null,
                    'resourceValues' => $game->tierListResource?->values->map(function ($value) use ($character) {
                        $alias = $value->aliasFor($character);

                        return [
                            'idResources_values' => $value->idResources_values,
                            'value' => $alias?->alias ?? $value->value,
                            'icon' => ($alias?->icon ?? $value->icon) ? Storage::url($alias?->icon ?? $value->icon) : null,
                        ];
                    })->values() ?? collect(),
                ])->values(),
                'resource' => $game->tierListResource ? [
                    'idgame_resources' => $game->tierListResource->idgame_resources,
                    'text_name' => $game->tierListResource->text_name,
                ] : null,
            ],
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
                    'resources_values_idResources_values' => $entry['resources_values_idResources_values'] ?? null,
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
        $tierList->load('game', 'user', 'entries.character', 'entries.resourceValue.characterAliases');
        $tierList->increment('views');

        return view('tier-lists.show', ['tierList' => $tierList]);
    }
}
