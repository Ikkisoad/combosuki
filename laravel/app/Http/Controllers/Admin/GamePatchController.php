<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GamePatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GamePatchController extends Controller
{
    public function index(Game $game): View
    {
        $patches = GamePatch::where('game_idgame', $game->idgame)->orderByDesc('released_at')->get();

        return view('admin.patches.index', ['game' => $game, 'patches' => $patches]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Add,Update,Delete'],
            'idgame_patch' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
            'label' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:10'],
            'released_at' => ['required_if:action,Add', 'nullable', 'date'],
        ]);

        // TODO: record which user made this edit once an audit/edit-log exists
        if ($validated['action'] === 'Add') {
            return $this->add($game, $validated);
        }

        if ($validated['action'] === 'Update') {
            return $this->updatePatch($game, $validated);
        }

        return $this->delete($game, $validated);
    }

    private function add(Game $game, array $validated): RedirectResponse
    {
        if ($this->labelConflicts($game, $validated['label'])) {
            return back()->withErrors(["A patch labeled \"{$validated['label']}\" already exists for this game."]);
        }

        $releasedAt = $validated['released_at'];
        $current = GamePatch::where('game_idgame', $game->idgame)->current()->first();

        if ($current && $releasedAt < $current->released_at->toDateString()) {
            return back()->withErrors(['The new patch\'s start date must be on or after the current patch\'s start date.']);
        }

        DB::transaction(function () use ($game, $validated, $releasedAt, $current) {
            if ($current) {
                $current->update(['ended_at' => $releasedAt]);
            }

            GamePatch::create([
                'game_idgame' => $game->idgame,
                'label' => $validated['label'],
                'released_at' => $releasedAt,
                'ended_at' => null,
            ]);
        });

        return redirect()->route('admin.patches.index', $game)->with('status', 'Patch added.');
    }

    private function updatePatch(Game $game, array $validated): RedirectResponse
    {
        $patch = GamePatch::where('game_idgame', $game->idgame)->where('idgame_patch', $validated['idgame_patch'])->first();

        if (! $patch) {
            return back()->withErrors(['Patch not found.']);
        }

        if ($this->labelConflicts($game, $validated['label'], exceptId: $patch->idgame_patch)) {
            return back()->withErrors(["A patch labeled \"{$validated['label']}\" already exists for this game."]);
        }

        $releasedAt = $validated['released_at'] ?? null;

        if ($releasedAt !== null && $releasedAt !== $patch->released_at->toDateString()) {
            if (! $patch->isCurrent()) {
                return back()->withErrors(['Only the current patch\'s start date can be changed. Delete and recreate a historical patch to correct it.']);
            }

            DB::transaction(function () use ($game, $patch, $releasedAt) {
                $previous = GamePatch::where('game_idgame', $game->idgame)
                    ->whereDate('ended_at', $patch->released_at->toDateString())
                    ->first();

                $previous?->update(['ended_at' => $releasedAt]);

                $patch->update(['released_at' => $releasedAt]);
            });
        }

        $patch->update(['label' => $validated['label']]);

        return redirect()->route('admin.patches.index', $game)->with('status', 'Patch updated.');
    }

    /**
     * Deleting a patch folds its date range into a neighbor so the
     * timeline never gets a gap: the previous patch (the one that ends
     * where this one starts) absorbs it by extending its own end date to
     * this patch's end date, if a previous patch exists; otherwise the next
     * patch (the one that starts where this one ends) absorbs it by
     * extending its own start date back to this patch's start date.
     * Deleting the current (open-ended) patch has no "next" by definition,
     * so its previous patch — if any — simply reopens as the new current
     * patch, matching prior behavior. Combos referencing the deleted patch
     * move to whichever neighbor absorbed its range, so nothing is silently
     * unlinked as long as a neighbor exists.
     */
    private function delete(Game $game, array $validated): RedirectResponse
    {
        $patch = GamePatch::where('game_idgame', $game->idgame)->where('idgame_patch', $validated['idgame_patch'])->first();

        if (! $patch) {
            return back()->withErrors(['Patch not found.']);
        }

        DB::transaction(function () use ($game, $patch) {
            $previous = GamePatch::where('game_idgame', $game->idgame)
                ->whereDate('ended_at', $patch->released_at->toDateString())
                ->first();

            $next = $patch->ended_at
                ? GamePatch::where('game_idgame', $game->idgame)
                    ->whereDate('released_at', $patch->ended_at->toDateString())
                    ->first()
                : null;

            if ($previous) {
                $previous->update(['ended_at' => $patch->ended_at]);
            } elseif ($next) {
                $next->update(['released_at' => $patch->released_at]);
            }

            $absorbing = $previous ?? $next;

            if ($absorbing) {
                $patch->combos()->update(['patch_idgame_patch' => $absorbing->idgame_patch]);
            }

            $patch->delete();
        });

        return redirect()->route('admin.patches.index', $game)->with('status', 'Patch deleted.');
    }

    private function labelConflicts(Game $game, string $label, ?int $exceptId = null): bool
    {
        return GamePatch::where('game_idgame', $game->idgame)
            ->whereRaw('LOWER(label) = ?', [Str::lower($label)])
            ->when($exceptId, fn ($q) => $q->where('idgame_patch', '!=', $exceptId))
            ->exists();
    }
}
