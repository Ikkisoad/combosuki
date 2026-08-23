<?php

namespace App\Services;

use App\Models\CombleAttempt;

class CombleStats
{
    /**
     * Global, all-time distribution across every Comble puzzle ever played
     * (not scoped to a single day) — the community equivalent of Wordle's
     * per-player stats, since Comble has no persistent player identity.
     */
    public function summary(): array
    {
        $rows = CombleAttempt::query()
            ->selectRaw('guesses, won, COUNT(*) as total')
            ->groupBy('guesses', 'won')
            ->get();

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 'lost' => 0];

        foreach ($rows as $row) {
            $key = $row->won ? (int) $row->guesses : 'lost';

            if (array_key_exists($key, $distribution)) {
                $distribution[$key] += (int) $row->total;
            }
        }

        $totalAttempts = array_sum($distribution);
        $totalWins = $totalAttempts - $distribution['lost'];
        $totalPerfect = CombleAttempt::where('perfect', true)->count();

        return [
            'totalAttempts' => $totalAttempts,
            'totalWins' => $totalWins,
            'winRate' => $totalAttempts > 0 ? round($totalWins / $totalAttempts * 100, 1) : 0.0,
            'totalPerfect' => $totalPerfect,
            'distribution' => $distribution,
        ];
    }
}
