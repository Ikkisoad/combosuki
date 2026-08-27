<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Site-wide switches. Mirrors DonationController, the existing
 * singleton-settings controller in this namespace.
 */
class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', ['settings' => SiteSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('discord_integration_enabled');

        SiteSetting::current()->update(['discord_integration_enabled' => $enabled]);

        // Worth an audit line: switching this off signs nobody out, but it
        // does lock out every account whose only credential is Discord.
        Log::info('Discord integration flag changed.', [
            'enabled' => $enabled,
            'by_user_iduser' => $request->user()->iduser,
        ]);

        SiteSetting::forgetCurrent();

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }
}
