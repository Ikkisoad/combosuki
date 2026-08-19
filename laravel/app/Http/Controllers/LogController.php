<?php

namespace App\Http\Controllers;

use App\Models\Logs;
use Illuminate\View\View;

class LogController extends Controller
{
    public function index(): View
    {
        $logs = Logs::orderByDesc('date')->orderByDesc('idlog')->get();

        return view('logs.index', ['logs' => $logs]);
    }
}
