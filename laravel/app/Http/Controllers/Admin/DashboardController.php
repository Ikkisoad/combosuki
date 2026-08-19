<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\Game;
use App\Models\ListModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $combos = $this->comboQuery($request)
            ->with(['character.game', 'user'])
            ->orderByDesc('idcombo')
            ->paginate(25, ['*'], 'combo_page')
            ->withQueryString();

        $lists = ListModel::query()
            ->with(['game', 'user'])
            ->when($request->filled('list_search'), function ($query) use ($request) {
                $query->where('list_name', 'like', '%'.$request->string('list_search').'%');
            })
            ->orderByDesc('idlist')
            ->paginate(25, ['*'], 'list_page')
            ->withQueryString();

        $games = Game::query()
            ->withCount(['characters', 'lists'])
            ->when($request->filled('game_search'), function ($query) use ($request) {
                $query->where('name', 'like', '%'.$request->string('game_search').'%');
            })
            ->orderByDesc('idgame')
            ->paginate(25, ['*'], 'game_page')
            ->withQueryString();

        return view('admin.dashboard.index', [
            'combos' => $combos,
            'lists' => $lists,
            'games' => $games,
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'combo_ids' => ['array'],
            'combo_ids.*' => ['integer'],
            'list_ids' => ['array'],
            'list_ids.*' => ['integer'],
            'game_ids' => ['array'],
            'game_ids.*' => ['integer'],
            'combo_delete_all_matching' => ['nullable', 'boolean'],
            'combo_search' => ['required_if:combo_delete_all_matching,1', 'nullable', 'string'],
        ]);

        $comboIds = $validated['combo_ids'] ?? [];
        $listIds = $validated['list_ids'] ?? [];
        $gameIds = $validated['game_ids'] ?? [];
        $deleteAllMatching = $request->boolean('combo_delete_all_matching');

        if (empty($comboIds) && empty($listIds) && empty($gameIds) && ! $deleteAllMatching) {
            return redirect()->route('admin.dashboard')->with('error', 'No entries selected.');
        }

        $deleted = DB::transaction(function () use ($request, $comboIds, $listIds, $gameIds, $deleteAllMatching) {
            $comboCount = $deleteAllMatching
                ? $this->comboQuery($request)->delete()
                : ($comboIds ? Combo::whereIn('idcombo', $comboIds)->delete() : 0);

            $listCount = $listIds ? ListModel::whereIn('idlist', $listIds)->delete() : 0;

            $gameCount = 0;

            if ($gameIds) {
                Game::whereIn('idgame', $gameIds)->get()->each(function (Game $game) {
                    // FK cascades handle characters/combos/resources/buttons, but
                    // list.game_idgame is nullOnDelete (lists can be cross-game), so
                    // the game's own lists need explicit deletion, matching the
                    // single-game delete flow in Admin\GameSettingsController.
                    $game->lists()->each(function (ListModel $list) {
                        $list->combos()->detach();
                        $list->categories()->delete();
                        $list->delete();
                    });

                    $game->delete();
                });

                $gameCount = count($gameIds);
            }

            return $comboCount + $listCount + $gameCount;
        });

        return redirect()->route('admin.dashboard')->with('status', "Deleted {$deleted} ".str('entry')->plural($deleted).'.');
    }

    /**
     * Shared between index() and destroy() so "delete all matching" deletes
     * exactly what the filtered list currently shows, not a stale count.
     */
    private function comboQuery(Request $request): Builder
    {
        return Combo::query()
            ->when($request->filled('combo_search'), function ($query) use ($request) {
                $term = $request->string('combo_search');
                $query->where(function ($q) use ($term) {
                    $q->where('combo', 'like', "%{$term}%")
                        ->orWhere('author', 'like', "%{$term}%")
                        ->orWhere('comments', 'like', "%{$term}%");
                });
            })
            ->when($request->boolean('combo_unverified'), function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('verified')->orWhere('verified', 0);
                });
            });
    }
}
