<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PreferenceController extends Controller
{
    private const COOKIE_MINUTES = 60 * 24 * 365 * 10;

    public function edit(Request $request): View
    {
        return view('preferences.edit', [
            'color' => $request->cookie('color', '920000'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'color' => ['required', 'regex:/^#?[0-9a-fA-F]{6}$/'],
        ]);

        $color = ltrim($validated['color'], '#');

        return redirect()->route('preferences.edit')
            ->with('status', 'Saved.')
            ->cookie('color', $color, self::COOKIE_MINUTES);
    }
}
