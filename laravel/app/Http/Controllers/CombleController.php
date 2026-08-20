<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Services\CombleDailyCombo;
use App\Services\CombleGuessEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CombleController extends Controller
{
    private const COOKIE_NAME = 'comble';

    private const COOKIE_MINUTES = 60 * 24 * 365 * 10;

    private const MAX_GUESSES = 5;

    public function __construct(
        private CombleDailyCombo $dailyCombo,
        private CombleGuessEvaluator $evaluator,
    ) {}

    public function show(Request $request, ?string $date = null): View
    {
        $day = $this->resolveDate($date);
        $target = $this->dailyCombo->forDate($day);
        $game = $target->character->game;

        $guesses = $this->evaluateGuesses($this->picksFromCookie($request, $day), $target);
        $won = collect($guesses)->contains('won', true);
        $finished = $won || count($guesses) >= self::MAX_GUESSES;

        return view('comble.show', [
            'game' => $game,
            'target' => $target,
            'guesses' => $guesses,
            'finished' => $finished,
            'won' => $won,
            'remaining' => self::MAX_GUESSES - count($guesses),
            'catalog' => $this->catalog(),
            'shareText' => $finished ? $this->shareText($guesses, $won, $day) : null,
            'day' => $day,
            'isToday' => $day->isToday(),
            'previousDay' => $day->copy()->subDay(),
            'nextDay' => $day->isToday() ? null : $day->copy()->addDay(),
        ]);
    }

    public function guess(Request $request, ?string $date = null): RedirectResponse
    {
        $day = $this->resolveDate($date);
        $target = $this->dailyCombo->forDate($day);

        $redirectRoute = $day->isToday() ? 'comble.show' : 'comble.show.date';
        $redirectParams = $day->isToday() ? [] : ['date' => $day->toDateString()];

        $picks = $this->picksFromCookie($request, $day);
        $guesses = $this->evaluateGuesses($picks, $target);
        $finished = collect($guesses)->contains('won', true) || count($guesses) >= self::MAX_GUESSES;

        if ($finished) {
            return redirect()->route($redirectRoute, $redirectParams)->with('error', 'That Comble puzzle is already finished.');
        }

        $validated = $request->validate([
            'game_id' => ['required', 'integer', 'exists:game,idgame'],
            'character_id' => [
                'required', 'integer',
                Rule::exists('character', 'idcharacter')->where('game_idgame', $request->input('game_id')),
            ],
            'listing_type_id' => [
                'required', 'integer',
                Rule::exists('game_entry', 'entryid')->where('gameid', $request->input('game_id')),
            ],
            'damage' => ['required', 'numeric', 'min:0'],
        ]);

        $picks[] = [
            (int) $validated['game_id'],
            (int) $validated['character_id'],
            (int) $validated['listing_type_id'],
            (float) $validated['damage'],
        ];

        $cookiePayload = json_encode(['picks' => $picks]);

        return redirect()->route($redirectRoute, $redirectParams)
            ->cookie($this->cookieName($day), $cookiePayload, self::COOKIE_MINUTES);
    }

    /**
     * Resolves the requested date to a start-of-day Carbon instance. No
     * lower bound: since the day's target is a pure function of the
     * currently-eligible combos, any past date resolves to something
     * playable. Future dates and malformed calendar dates (e.g. Feb 30,
     * which the route's \d{4}-\d{2}-\d{2} pattern lets through) 404.
     */
    private function resolveDate(?string $date): Carbon
    {
        if ($date === null) {
            return now()->startOfDay();
        }

        try {
            $day = Carbon::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            abort(404);
        }

        abort_if($day->format('Y-m-d') !== $date, 404);
        abort_if($day->gt(now()->startOfDay()), 404);

        return $day;
    }

    private function cookieName(Carbon $day): string
    {
        return self::COOKIE_NAME.'_'.$day->toDateString();
    }

    /**
     * Reads raw [game_id, character_id, listing_type_id, damage] picks from
     * the cookie for this specific day. Each day gets its own cookie, so
     * playing an old puzzle never touches today's (or any other day's)
     * progress.
     */
    private function picksFromCookie(Request $request, Carbon $day): array
    {
        $raw = $request->cookie($this->cookieName($day));

        if (! $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_slice(array_values($decoded['picks'] ?? []), 0, self::MAX_GUESSES);
    }

    /**
     * Correctness/hints are never stored, only the raw picks: they're
     * recomputed against the day's target every time so there's a single
     * source of truth.
     */
    private function evaluateGuesses(array $picks, Combo $target): array
    {
        $guesses = [];

        foreach ($picks as $pick) {
            $game = Game::find($pick[0] ?? null);
            $character = Character::find($pick[1] ?? null);
            $listingType = GameEntry::find($pick[2] ?? null);
            $damage = isset($pick[3]) ? (float) $pick[3] : null;

            if (! $game || ! $character || ! $listingType) {
                continue;
            }

            $guesses[] = array_merge(
                ['game' => $game, 'character' => $character, 'listing_type' => $listingType, 'damage' => $damage],
                $this->evaluator->evaluate($target, $game, $character, $listingType, $damage)
            );
        }

        return $guesses;
    }

    /**
     * A Wordle-style shareable summary: one row of squares per guess (green
     * for a correct column, red for a wrong one), no spoilers.
     */
    private function shareText(array $guesses, bool $won, Carbon $day): string
    {
        $rows = array_map(fn (array $guess) => implode('', [
            $guess['game_correct'] ? '🟩' : '🟥',
            $guess['character_correct'] ? '🟩' : '🟥',
            $guess['type_correct'] ? '🟩' : '🟥',
            match ($guess['damage_hint']) {
                'equal' => '🎯',
                'higher' => '⬆️',
                'lower' => '⬇️',
                default => '❔',
            },
        ]), $guesses);

        $score = $won ? count($guesses).'/'.self::MAX_GUESSES : 'X/'.self::MAX_GUESSES;

        $link = $day->isToday()
            ? route('comble.show')
            : route('comble.show.date', ['date' => $day->toDateString()]);

        return implode("\n", array_merge(
            ['Comble '.$day->toDateString().' '.$score, ''],
            $rows,
            ['', $link]
        ));
    }

    private function catalog(): array
    {
        $games = Game::where('complete', '>', 0)->orderBy('name')->get(['idgame', 'name']);

        $characters = Character::whereIn('game_idgame', $games->pluck('idgame'))
            ->orderBy('name')
            ->get(['idcharacter', 'name', 'game_idgame']);

        $types = GameEntry::whereIn('gameid', $games->pluck('idgame'))
            ->orderBy('order')
            ->orderBy('title')
            ->get(['entryid', 'title', 'gameid']);

        return [
            'games' => $games->map(fn (Game $g) => ['id' => $g->idgame, 'name' => $g->name])->values(),
            'charactersByGame' => $characters->groupBy('game_idgame')
                ->map(fn ($group) => $group->map(fn (Character $c) => ['id' => $c->idcharacter, 'name' => $c->name])->values()),
            'typesByGame' => $types->groupBy('gameid')
                ->map(fn ($group) => $group->map(fn (GameEntry $t) => ['id' => $t->entryid, 'title' => $t->title])->values()),
        ];
    }
}
