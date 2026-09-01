<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\GameEntry;
use App\Models\ListModel;
use App\Models\ListPage;
use App\Models\ListPageCanvasEdge;
use Illuminate\View\View;

class ListCanvasController extends Controller
{
    public function edit(ListModel $list, ListPage $page): View
    {
        $this->authorize('update', $list);

        abort_if($page->idList !== $list->idlist || ! $page->isCanvas(), 404);

        $nodes = $page->canvasNodes()->with(['combo.character', 'combo.listingType'])->get();
        $edges = ListPageCanvasEdge::whereIn('idFromNode', $nodes->pluck('idCanvasNode'))->get();

        $characters = $list->game_idgame !== null
            ? Character::where('game_idgame', $list->game_idgame)->orderBy('name')->get()
            : collect();

        $listingTypes = $list->game_idgame !== null
            ? GameEntry::where('gameid', $list->game_idgame)->orderBy('order')->orderBy('title')->get()
            : collect();

        return view('lists.manage.canvas', [
            'list' => $list,
            'page' => $page,
            'nodes' => $nodes,
            'edges' => $edges,
            'characters' => $characters,
            'listingTypes' => $listingTypes,
        ]);
    }
}
