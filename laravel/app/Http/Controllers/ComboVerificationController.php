<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use Illuminate\Http\RedirectResponse;

class ComboVerificationController extends Controller
{
    public function store(Combo $combo): RedirectResponse
    {
        $this->authorize('verify', $combo);

        $combo->markVerifiedBy(auth()->user());

        return redirect()->back()->with('status', 'Combo verified.');
    }
}
