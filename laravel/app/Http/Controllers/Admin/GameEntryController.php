<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameEntry;
use App\Services\GamePasswordChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameEntryController extends Controller
{
    public function __construct(private GamePasswordChecker $passwordChecker) {}

    public function index(Game $game): View
    {
        $entries = GameEntry::where('gameid', $game->idgame)->orderBy('order')->orderBy('title')->get();

        return view('admin.entries.index', ['game' => $game, 'entries' => $entries]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Add,Update,Delete'],
            'entry' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:45'],
            'order' => ['nullable', 'numeric'],
            'entryid' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
            'gamePass' => ['required', 'string', 'max:16'],
        ]);

        if (! $this->passwordChecker->check($game, $validated['gamePass'])) {
            return back()->with('error', 'Incorrect game password.');
        }

        if ($validated['action'] === 'Add') {
            GameEntry::create(['title' => $validated['entry'], 'gameid' => $game->idgame, 'order' => $validated['order'] ?? null]);
        } elseif ($validated['action'] === 'Update') {
            GameEntry::where('entryid', $validated['entryid'])
                ->where('gameid', $game->idgame)
                ->update(['title' => $validated['entry'], 'order' => $validated['order'] ?? null]);
        } else {
            GameEntry::where('entryid', $validated['entryid'])->where('gameid', $game->idgame)->delete();
        }

        return redirect()->route('admin.entries.index', $game)->with('status', 'Saved.');
    }
}
