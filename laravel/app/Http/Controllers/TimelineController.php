<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use Illuminate\View\View;

class TimelineController extends Controller
{
    public function index(): View
    {
        $combos = Combo::with(['character.game', 'listingType', 'user'])
            ->whereHas('character.game')
            ->orderByDesc('submited')
            ->paginate(30);

        return view('timeline.index', ['combos' => $combos]);
    }
}
