<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\Game;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UnverifiedCombosController extends Controller
{
    public function index(Game $game): View
    {
        $combos = $this->unverifiedCombosQuery($game)->paginate(25);

        // A per-game moderator (can:update,game) doesn't necessarily pass
        // ComboPolicy::verify's global isTrusted() check (see bulkVerify()),
        // so the bulk-verify bar is only offered when at least one visible
        // row is actually theirs to verify.
        $canBulkVerify = $combos->getCollection()->contains(fn (Combo $combo) => Gate::allows('verify', $combo));

        return view('admin.unverified-combos.index', ['game' => $game, 'combos' => $combos, 'canBulkVerify' => $canBulkVerify]);
    }

    public function bulkVerify(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'combo_ids' => ['required', 'array'],
            'combo_ids.*' => ['integer'],
        ]);

        // Re-scoped through the same query as index() so a manually crafted
        // request can't verify a combo outside this game (or an already
        // handled one); Gate::allows() re-checks per combo since this route
        // only requires can:update,game, which a per-game-only moderator can
        // satisfy without passing ComboPolicy::verify's global isTrusted().
        $combos = $this->unverifiedCombosQuery($game)
            ->whereIn('idcombo', $validated['combo_ids'])
            ->get();

        $verified = 0;

        foreach ($combos as $combo) {
            if (Gate::allows('verify', $combo)) {
                $combo->markVerifiedBy($request->user());
                $verified++;
            }
        }

        if ($verified === 0) {
            return redirect()->route('admin.unverified-combos.index', $game)->with('error', 'No combos were verified.');
        }

        return redirect()->route('admin.unverified-combos.index', $game)
            ->with('status', "Verified {$verified} ".str('combo')->plural($verified).'.');
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
