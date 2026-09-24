<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\CombleDayView;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use App\Services\CombleAttemptRecorder;
use App\Services\CombleDailyCombo;
use App\Services\CombleDiscordProgress;
use App\Services\CombleGuessEvaluator;
use App\Services\CombleStats;
use App\Support\DailyGameClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

/**
 * Comble played as a Discord Activity — the same puzzle CombleController
 * (web) and DiscordCombleGame (bot) already serve, on a third surface with
 * no Laravel session available (see routes/activity.php). Progress is kept
 * in CombleDiscordProgress's cache, keyed the same way DiscordCombleGame
 * keys it, so a player's progress is shared between the bot and the
 * Activity. Only today's puzzle is playable here, matching the bot — the
 * web version's past-date archive isn't exposed to either.
 *
 * The Activity's own *page* is CombleController::show() (comble.show) —
 * Discord's Root URL Mapping always loads that domain's "/" as the initial
 * document, and resources/js/comble.js's bootDiscordActivity() calls
 * state()/guess() here afterward once it detects the page is framed. Both
 * are gated behind the `activity.auth` middleware alias
 * (VerifyActivityToken), which resolves the verified Discord user id onto
 * the request.
 *
 * guess() additionally announces a finished puzzle back into the text
 * channel `/csk comble` was launched from — see announceFinish() — so
 * playing through the Activity still surfaces a visible result in-channel,
 * matching what the chat-based DiscordCombleGame flow shows inline.
 */
class ActivityCombleController extends Controller
{
    private const MAX_GUESSES = 5;

    public function __construct(
        private CombleDailyCombo $dailyCombo,
        private CombleGuessEvaluator $evaluator,
        private CombleStats $stats,
        private CombleAttemptRecorder $attemptRecorder,
        private CombleDiscordProgress $progress,
    ) {}

    public function state(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $day = DailyGameClock::today();
        CombleDayView::recordView($day);

        $target = $this->dailyCombo->forDate($day);
        $game = $target->character->game;
        $guesses = $this->evaluateGuesses($this->progress->picks($userId, $day), $target);

        return response()->json([
            'html' => view('activity._comble-game', $this->gameState($day, $target, $game, $guesses))->render(),
        ]);
    }

    public function guess(Request $request): JsonResponse
    {
        $userId = $this->userId($request);
        $day = DailyGameClock::today();
        $target = $this->dailyCombo->forDate($day);
        $game = $target->character->game;

        $guesses = $this->evaluateGuesses($this->progress->picks($userId, $day), $target);
        $finished = collect($guesses)->contains('won', true) || count($guesses) >= self::MAX_GUESSES;

        if ($finished) {
            return response()->json(['error' => 'That Comble puzzle is already finished.'], 409);
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
            'starter' => ['nullable', 'string', 'max:6'],
        ]);

        $picks = $this->progress->appendPick($userId, $day, [
            (int) $validated['game_id'],
            (int) $validated['character_id'],
            (int) $validated['listing_type_id'],
            (float) $validated['damage'],
            $validated['starter'] ?? null,
        ]);

        $guesses = $this->evaluateGuesses($picks, $target);

        $this->attemptRecorder->recordIfFinished(
            $day,
            $this->progress->visitorKey($userId),
            null,
            $guesses,
            self::MAX_GUESSES,
        );

        $state = $this->gameState($day, $target, $game, $guesses);

        // The early-return above guarantees this guess is the one that
        // *first* finishes the puzzle (a request against an already-finished
        // puzzle never reaches here), so this fires exactly once per player
        // per day.
        if ($state['finished']) {
            $this->announceFinish($userId, $state['shareText']);
        }

        return response()->json([
            'html' => view('activity._comble-game', $state)->render(),
        ]);
    }

    /**
     * Posts the finished puzzle's share-style result — squares and score
     * only, never the answer, same privacy rule as
     * DiscordCombleGame::publicStatus() — into whichever text channel
     * `/csk comble` was launched from (see
     * CombleDiscordProgress::rememberChannel()), so a player who finishes
     * through the Activity still gets a visible result in-channel. Silently
     * no-ops without a remembered channel (e.g. launched from a DM, or the
     * cache entry expired) and is deferred past the response so a slow or
     * failed Discord API call never delays the player's own result.
     *
     * Names the player by username rather than an `<@id>` mention — a
     * finished-puzzle announcement doesn't need to page anyone, just credit
     * them. The "Play now" button launches the Activity (interaction
     * response type 12, same as `/csk comble` itself) for whichever channel
     * member clicks it — see DiscordInteractionController::handleCombleComponent().
     */
    private function announceFinish(string $userId, string $shareText): void
    {
        $channelId = $this->progress->channelFor($userId);
        $botToken = config('services.discord.bot_token');

        if ($channelId === null || ! $botToken) {
            return;
        }

        dispatch(function () use ($channelId, $botToken, $userId, $shareText) {
            try {
                $username = $this->fetchUsername($botToken, $userId);

                Http::withToken($botToken, 'Bot')->asJson()->timeout(5)->post(
                    "https://discord.com/api/v10/channels/{$channelId}/messages",
                    [
                        'content' => "{$username} finished today's Comble!\n\n{$shareText}",
                        'components' => [[
                            'type' => 1,
                            'components' => [[
                                'type' => 2,
                                'style' => 1,
                                'label' => 'Play now',
                                'custom_id' => 'cb:launch',
                            ]],
                        ]],
                    ]
                );
            } catch (\Throwable $e) {
                report($e);
            }
        })->afterResponse();
    }

    /** Best-effort display name for the announcement — falls back to a generic label rather than ever leaking a raw snowflake into the message. */
    private function fetchUsername(string $botToken, string $userId): string
    {
        try {
            $response = Http::withToken($botToken, 'Bot')->timeout(5)->get("https://discord.com/api/v10/users/{$userId}");

            if ($response->successful()) {
                return $response->json('global_name') ?? $response->json('username') ?? 'A player';
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return 'A player';
    }

    private function userId(Request $request): string
    {
        return $request->attributes->get('discord_user_id');
    }

    /**
     * The view data for the guessing widget — mirrors
     * CombleController::gameState()'s shape exactly (the forked
     * activity._comble-game partial expects the same fields) but is kept as
     * its own copy rather than a shared helper, same as DiscordCombleGame's
     * own duplicated evaluateGuesses(): each surface's transport (cookie vs
     * cache) stays free to evolve without coupling to the others' plumbing.
     * Only the rule-bearing services (CombleGuessEvaluator etc.) are
     * actually shared.
     */
    private function gameState(Carbon $day, Combo $target, Game $game, array $guesses): array
    {
        $won = collect($guesses)->contains('won', true);
        $finished = $won || count($guesses) >= self::MAX_GUESSES;
        $lastGuess = $guesses === [] ? null : $guesses[array_key_last($guesses)];

        return [
            'game' => $game,
            'target' => $target,
            'guesses' => $guesses,
            'finished' => $finished,
            'won' => $won,
            'remaining' => self::MAX_GUESSES - count($guesses),
            'shareText' => $finished ? $this->shareText($guesses, $won, $day) : null,
            'day' => $day,
            'isToday' => true,
            'stickyGameId' => $lastGuess && $lastGuess['game_correct'] ? $lastGuess['game']->idgame : null,
            'stickyCharacterId' => $lastGuess && $lastGuess['character_correct'] ? $lastGuess['character']->idcharacter : null,
            'stickyTypeId' => $lastGuess && $lastGuess['type_correct'] ? $lastGuess['listing_type']->entryid : null,
            'stickyTypeTitle' => $lastGuess && $lastGuess['type_correct'] ? $lastGuess['listing_type']->title : null,
            'stickyStarter' => $lastGuess && $lastGuess['starter_result'] === 'partial' ? $lastGuess['starter'] : null,
            // Unlike the other fields, damage isn't a discrete pick to
            // re-select — it's a number the player nudges up/down using the
            // damage_hint, so it stays prefilled from the last guess
            // regardless of whether that guess was close.
            'stickyDamage' => $lastGuess['damage'] ?? null,
            'stats' => $this->stats->summary($day),
        ];
    }

    /**
     * Correctness/hints are never stored, only the raw picks: they're
     * recomputed against the day's target every time so there's a single
     * source of truth — mirrors CombleController::evaluateGuesses() and
     * DiscordCombleGame::evaluateGuesses().
     */
    private function evaluateGuesses(array $picks, Combo $target): array
    {
        $guesses = [];

        foreach ($picks as $pick) {
            $game = Game::find($pick[0] ?? null);
            $character = Character::find($pick[1] ?? null);
            $listingType = GameEntry::find($pick[2] ?? null);
            $damage = isset($pick[3]) ? (float) $pick[3] : null;
            $starter = $pick[4] ?? null;

            if (! $game || ! $character || ! $listingType) {
                continue;
            }

            $guesses[] = array_merge(
                ['game' => $game, 'character' => $character, 'listing_type' => $listingType, 'damage' => $damage, 'starter' => $starter],
                $this->evaluator->evaluate($target, $game, $character, $listingType, $damage, $starter)
            );
        }

        return $guesses;
    }

    /** Mirrors CombleController::shareText() — see that method for the format's rationale. */
    private function shareText(array $guesses, bool $won, Carbon $day): string
    {
        $rows = array_map(fn (array $guess) => implode('', [
            $guess['game_correct'] ? '🟩' : '🟥',
            $guess['character_correct'] ? '🟩' : '🟥',
            $guess['type_correct'] ? '🟩' : '🟥',
            match ($guess['starter_result']) {
                'correct' => '🔵',
                'partial' => '🟠',
                default => '🔴',
            },
            match ($guess['damage_hint']) {
                'equal' => '🎯',
                'higher_close' => '⬆️',
                'higher_far' => '⏫',
                'lower_close' => '⬇️',
                'lower_far' => '⏬',
                default => '❔',
            },
        ]), $guesses);

        $score = $won ? count($guesses).'/'.self::MAX_GUESSES : 'X/'.self::MAX_GUESSES;

        return implode("\n", array_merge(
            ['Comble '.$day->toDateString().' '.$score, ''],
            $rows,
            ['', "<{$this->webUrl()}>"]
        ));
    }

    private function webUrl(): string
    {
        return route('comble.show');
    }
}
