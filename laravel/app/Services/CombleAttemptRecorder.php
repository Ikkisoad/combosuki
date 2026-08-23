<?php

namespace App\Services;

use App\Models\CombleAttempt;
use Illuminate\Support\Carbon;

/**
 * Records one completed-Comble-game row the moment a set of guesses becomes
 * finished (won, or all guesses used). Shared by the web flow
 * (CombleController) and the Discord bot flow (DiscordCombleGame) so the
 * "perfect score" definition and dedup behavior can't drift between the two
 * — both feed the same CombleStats numbers.
 */
class CombleAttemptRecorder
{
    /**
     * `$guesses` is the evaluated-guess array (CombleGuessEvaluator::evaluate()
     * shape) for everything picked so far. `firstOrCreate` plus the
     * (day, visitor_key) unique index means calling this again for an
     * already-recorded day/visitor is a no-op, so callers don't need to track
     * "was this already finished before this guess" themselves.
     */
    public function recordIfFinished(Carbon $day, string $visitorKey, ?int $userId, array $guesses, int $maxGuesses): void
    {
        $winningGuess = collect($guesses)->firstWhere('won', true);
        $won = $winningGuess !== null;
        $finished = $won || count($guesses) >= $maxGuesses;

        if (! $finished) {
            return;
        }

        // Every guessable column right on the winning guess, not just the
        // two (game + character) that gate a win — type, starter and damage
        // are hint-only for winning purposes.
        $perfect = $won
            && $winningGuess['type_correct']
            && $winningGuess['starter_result'] === 'correct'
            && $winningGuess['damage_hint'] === 'equal';

        CombleAttempt::firstOrCreate(
            ['day' => $day->toDateString(), 'visitor_key' => $visitorKey],
            ['guesses' => count($guesses), 'won' => $won, 'perfect' => $perfect, 'user_iduser' => $userId],
        );
    }
}
