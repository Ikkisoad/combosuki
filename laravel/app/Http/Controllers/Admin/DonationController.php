<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonationProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DonationController extends Controller
{
    public function edit(): View
    {
        return view('admin.donation.edit', [
            'donation' => DonationProgress::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'string', 'max:45'],
            'goal' => ['required', 'numeric', 'min:0'],
            'raised' => ['required', 'numeric', 'min:0'],
        ]);

        DonationProgress::current()->update($validated);

        return redirect()->route('admin.donation.edit')->with('status', 'Donation progress updated.');
    }
}
