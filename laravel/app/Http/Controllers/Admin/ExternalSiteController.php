<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExternalSiteController extends Controller
{
    public function index(): View
    {
        $sites = ExternalSite::orderBy('order')->orderBy('title')->get();

        return view('admin.external-sites.index', ['sites' => $sites]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:Add,Update,Delete'],
            'title' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:100'],
            'url' => ['required_if:action,Add,Update', 'nullable', 'string', 'max:255'],
            'order' => ['nullable', 'numeric'],
            'id' => ['required_if:action,Update,Delete', 'nullable', 'integer'],
        ]);

        if ($validated['action'] === 'Add') {
            ExternalSite::create([
                'title' => $validated['title'],
                'url' => $validated['url'],
                'order' => $validated['order'] ?? null,
            ]);
        } elseif ($validated['action'] === 'Update') {
            ExternalSite::where('id', $validated['id'])->update([
                'title' => $validated['title'],
                'url' => $validated['url'],
                'order' => $validated['order'] ?? null,
            ]);
        } else {
            ExternalSite::where('id', $validated['id'])->delete();
        }

        return redirect()->route('admin.external-sites.index')->with('status', 'Saved.');
    }
}
