<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Link;
use App\Services\GamePasswordChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LinkController extends Controller
{
    public function __construct(private GamePasswordChecker $passwordChecker) {}

    public function index(Game $game): View
    {
        $links = Link::where('idGame', $game->idgame)->orderBy('Title')->get();

        return view('admin.links.index', ['game' => $game, 'links' => $links]);
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Add,Update,Delete'],
            'title' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:50'],
            'link' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:255'],
            'idLink' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
            'gamePass' => ['required', 'string', 'max:16'],
        ]);

        if (! $this->passwordChecker->check($game, $validated['gamePass'])) {
            return back()->with('error', 'Incorrect game password.');
        }

        if ($validated['action'] === 'Add') {
            Link::create(['idGame' => $game->idgame, 'Title' => $validated['title'], 'Link' => $validated['link']]);
        } elseif ($validated['action'] === 'Update') {
            Link::where('idLink', $validated['idLink'])
                ->where('idGame', $game->idgame)
                ->update(['Title' => $validated['title'], 'Link' => $validated['link']]);
        } else {
            Link::where('idLink', $validated['idLink'])->where('idGame', $game->idgame)->delete();
        }

        return redirect()->route('admin.links.index', $game)->with('status', 'Saved.');
    }
}
