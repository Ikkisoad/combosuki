<?php

namespace App\Services;

use App\Exceptions\DiscordInteractionUnauthorized;
use App\Models\Character;
use App\Models\Combo;
use App\Models\Game;
use App\Models\GameEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Drives Comble (the daily "guess the combo" puzzle — see CombleController)
 * through Discord: a game -> character -> type dropdown chain ending in a
 * damage Modal, restarted from the resulting status message after every
 * guess. Only today's puzzle is playable through the bot; the web version's
 * past-date archive isn't exposed here.
 *
 * Unlike DiscordComboWizard, accumulated guesses aren't threaded through
 * custom_id: they need to persist across a user's separate `/combo comble`
 * invocations for the same day, so they're kept server-side in cache, keyed
 * by Discord user id + date — mirroring the web version's per-day cookie,
 * just server-side since Discord interactions carry no cookies. Only the
 * raw picks are cached; correctness is always recomputed against the day's
 * target, same as the web controller.
 *
 * Game messages are public (not ephemeral), so anyone in the channel can
 * watch a puzzle in progress — that's the point. But it also means anyone
 * could click the message's own dropdowns, so the owner's Discord user id
 * rides along in every custom_id/Modal (the "u" state key) and every step
 * that isn't a fresh publicStatus() calls assertOwner() first, throwing
 * DiscordInteractionUnauthorized for the controller to turn into a private
 * bounce reply if the clicking user isn't the one who started the game.
 *
 * The public message itself never shows a guessed name or (once finished)
 * the answer — only colored squares — so it doesn't spoil the puzzle for
 * channel members who haven't played yet. The full, named breakdown is
 * privateSummary(), which the controller sends as a private (ephemeral)
 * follow-up to the owner alongside every public update.
 */
class DiscordCombleGame
{
    private const MAX_GUESSES = 5;

    /** Generous relative to the puzzle's 1-day relevance window, just to bound cache growth. */
    private const CACHE_TTL_DAYS = 3;

    private const MAX_CHOICES = 24;

    public function __construct(
        private CombleDailyCombo $dailyCombo,
        private CombleGuessEvaluator $evaluator,
        private CombleRevealer $revealer,
    ) {}

    public function start(string $userId): array
    {
        return $this->publicStatus($userId);
    }

    /**
     * Handle a MESSAGE_COMPONENT interaction (game/character dropdown) and
     * return the next step's response `data` object, sent back as an
     * UPDATE_MESSAGE (type 7) — except for the type dropdown, which the
     * controller must send back as a MODAL (type 9) using buildDamageModal()
     * instead of this method.
     */
    public function handleComponent(string $customId, array $values, string $userId): array
    {
        [, $action, , $stateRaw] = array_pad(explode(':', $customId, 4), 4, '');
        $state = $this->decodeState($stateRaw);
        $this->assertOwner($state, $userId);
        $selected = $values[0] ?? null;

        return match ($action) {
            'game' => $this->characterStep($this->withState($state, 'g', $selected), $userId),
            'char' => $this->typeStep($this->withState($state, 'c', $selected), $userId),
            default => $this->publicStatus($userId),
        };
    }

    /**
     * Build the damage-guess Modal (interaction response type 9), triggered
     * by selecting a type from typeStep()'s dropdown. The type answer arrives
     * via the interaction's `values`, not the custom_id — same reasoning as
     * DiscordComboWizard's resource selects.
     */
    public function buildDamageModal(string $customId, ?string $selectedTypeId, string $userId): array
    {
        [, , , $stateRaw] = array_pad(explode(':', $customId, 4), 4, '');
        $state = $this->decodeState($stateRaw);
        $this->assertOwner($state, $userId);
        $state = $this->withState($state, 't', $selectedTypeId);

        return [
            'title' => 'Guess the damage',
            'custom_id' => 'cb:dmgsubmit::'.$this->encodeState($state),
            'components' => [
                $this->actionRow([[
                    'type' => 4,
                    'custom_id' => 'damage',
                    'style' => 1,
                    'label' => 'Damage guess',
                    'required' => true,
                    'placeholder' => 'e.g. 3500',
                ]]),
            ],
        ];
    }

    /**
     * Handle the damage Modal's MODAL_SUBMIT interaction: record the guess
     * and return the updated status response `data` object, sent back as an
     * UPDATE_MESSAGE (type 7).
     */
    public function handleModalSubmit(string $customId, array $submittedRows, string $userId): array
    {
        [, , , $stateRaw] = array_pad(explode(':', $customId, 4), 4, '');
        $state = $this->decodeState($stateRaw);
        $this->assertOwner($state, $userId);

        $day = now()->startOfDay();
        $target = $this->dailyCombo->forDate($day);

        if (count($this->picks($userId, $day)) >= self::MAX_GUESSES) {
            return $this->publicStatus($userId);
        }

        $game = Game::find($state['g'] ?? null);
        $character = Character::find($state['c'] ?? null);
        $type = GameEntry::find($state['t'] ?? null);

        if (! $game || ! $character || ! $type) {
            return $this->publicStatus($userId, 'Something went wrong with that guess — please try again.');
        }

        $damageRaw = trim((string) (collect($submittedRows)->pluck('components.0')->first()['value'] ?? ''));

        if (! is_numeric($damageRaw) || (float) $damageRaw < 0) {
            return $this->publicStatus($userId, 'Damage must be a non-negative number.');
        }

        $this->appendPick($userId, $day, [
            $game->idgame,
            $character->idcharacter,
            $type->entryid,
            (float) $damageRaw,
        ]);

        return $this->publicStatus($userId);
    }

    private function characterStep(array $state, string $userId): array
    {
        $game = Game::find($state['g'] ?? null);

        if (! $game) {
            return $this->publicStatus($userId);
        }

        $characters = Character::where('game_idgame', $game->idgame)
            ->orderBy('name')
            ->limit(self::MAX_CHOICES + 1)
            ->get();

        $options = $characters->map(fn (Character $character) => [
            'label' => Str::limit($character->name, 100, ''),
            'value' => (string) $character->idcharacter,
        ])->all();

        $stateRaw = $this->encodeState($state);

        return [
            'embeds' => [['title' => 'Comble', 'description' => "**Game:** {$game->name}\nNow choose a character."]],
            'components' => [
                $this->actionRow([$this->select("cb:char::{$stateRaw}", 'Choose a character', $options)]),
            ],
        ];
    }

    private function typeStep(array $state, string $userId): array
    {
        $game = Game::find($state['g'] ?? null);
        $character = Character::find($state['c'] ?? null);

        if (! $game || ! $character) {
            return $this->publicStatus($userId);
        }

        $types = GameEntry::where('gameid', $game->idgame)
            ->orderBy('order')
            ->orderBy('title')
            ->limit(self::MAX_CHOICES + 1)
            ->get();

        $options = $types->map(fn (GameEntry $type) => [
            'label' => Str::limit($type->title, 100, ''),
            'value' => (string) $type->entryid,
        ])->all();

        $stateRaw = $this->encodeState($state);

        return [
            'embeds' => [['title' => 'Comble', 'description' => "**Game:** {$game->name}\n**Character:** {$character->name}\nNow choose a type — you'll be asked to guess the damage next."]],
            'components' => [
                $this->actionRow([$this->select("cb:type::{$stateRaw}", 'Choose a type', $options)]),
            ],
        ];
    }

    /**
     * The shared, public message: reveal progress, remaining-guess count,
     * and one row of squares per guess — never a guessed name or (once
     * finished) the answer, so it doesn't spoil the puzzle for anyone
     * watching who hasn't played yet.
     */
    private function publicStatus(string $userId, ?string $error = null): array
    {
        ['day' => $day, 'game' => $game, 'target' => $target, 'guesses' => $guesses, 'won' => $won, 'finished' => $finished] = $this->progress($userId);

        $lines = [];

        if ($error) {
            $lines[] = "⚠️ {$error}";
            $lines[] = '';
        }

        $lines[] = '```'.$this->revealer->renderPlain($game, $target->combo, count($guesses)).'```';
        $lines[] = '';
        $lines[] = $this->remainingLine($won, $finished, count($guesses));

        foreach ($guesses as $index => $guess) {
            $lines[] = ($index + 1).'. '.$this->squaresLine($guess);
        }

        $embed = ['title' => 'Comble — '.$day->toDateString(), 'description' => implode("\n", $lines)];

        $components = $finished
            ? [$this->actionRow([[
                'type' => 2,
                'style' => 5,
                'label' => 'Play on the site',
                'url' => rtrim(config('app.url'), '/').route('comble.show', absolute: false),
            ]])]
            : [$this->actionRow([$this->gameSelect($userId)])];

        // Not ephemeral: the game is posted publicly so other channel
        // members can see who's playing and how far they've gotten.
        return ['embeds' => [$embed], 'components' => $components];
    }

    /**
     * The full, named breakdown of $userId's progress — guessed game,
     * character, type, and damage per row, plus the answer once finished.
     * Meant to be sent as a private (ephemeral) follow-up alongside every
     * publicStatus() update, so the player still gets full detail without
     * it leaking into the public, watchable message.
     */
    public function privateSummary(string $userId): array
    {
        ['day' => $day, 'game' => $game, 'target' => $target, 'guesses' => $guesses, 'won' => $won, 'finished' => $finished] = $this->progress($userId);

        $lines = [];
        $lines[] = '```'.$this->revealer->renderPlain($game, $target->combo, count($guesses)).'```';
        $lines[] = '';
        $lines[] = $this->remainingLine($won, $finished, count($guesses));

        foreach ($guesses as $index => $guess) {
            $lines[] = ($index + 1).'. '.$this->detailedLine($guess);
        }

        if ($finished) {
            $lines[] = '';
            $lines[] = "**{$target->character->name}** — {$game->name}".($target->listingType ? " ({$target->listingType->title})" : '')
                .($target->damage !== null ? ' · '.number_format((float) $target->damage, 0, '', '.').' dmg' : '');
        }

        return [
            'embeds' => [['title' => 'Your Comble guesses (only visible to you)', 'description' => implode("\n", $lines)]],
            'flags' => 64,
        ];
    }

    /** Shared progress data behind both publicStatus() and privateSummary(). */
    private function progress(string $userId): array
    {
        $day = now()->startOfDay();
        $target = $this->dailyCombo->forDate($day);
        $game = $target->character->game;

        $guesses = $this->evaluateGuesses($this->picks($userId, $day), $target);
        $won = collect($guesses)->contains('won', true);
        $finished = $won || count($guesses) >= self::MAX_GUESSES;

        return compact('day', 'target', 'game', 'guesses', 'won', 'finished');
    }

    private function remainingLine(bool $won, bool $finished, int $guessCount): string
    {
        if ($finished) {
            return $won ? '**You got it!**' : '**Better luck tomorrow!**';
        }

        $remaining = self::MAX_GUESSES - $guessCount;

        return $remaining.' '.($remaining === 1 ? 'guess' : 'guesses').' left.';
    }

    private function squaresLine(array $guess): string
    {
        return implode(' ', [
            $guess['game_correct'] ? '🟩' : '🟥',
            $guess['character_correct'] ? '🟩' : '🟥',
            $guess['type_correct'] ? '🟩' : '🟥',
            $this->damageHintEmoji($guess['damage_hint']),
        ]);
    }

    private function detailedLine(array $guess): string
    {
        return implode(' ', [
            $guess['game_correct'] ? '🟩' : '🟥',
            $guess['game']->name,
            $guess['character_correct'] ? '🟩' : '🟥',
            $guess['character']->name,
            $guess['type_correct'] ? '🟩' : '🟥',
            $guess['listing_type']->title,
            $this->damageHintEmoji($guess['damage_hint']),
            $guess['damage'] !== null ? number_format($guess['damage'], 0, '', '.') : '—',
        ]);
    }

    private function damageHintEmoji(string $hint): string
    {
        return match ($hint) {
            'equal' => '🎯',
            'higher' => '⬆️',
            'lower' => '⬇️',
            default => '❔',
        };
    }

    private function gameSelect(string $ownerId): array
    {
        $games = Game::where('complete', '>', 0)->orderBy('name')->limit(self::MAX_CHOICES + 1)->get();

        $options = $games->map(fn (Game $game) => [
            'label' => Str::limit($game->name, 100, ''),
            'value' => (string) $game->idgame,
        ])->all();

        return $this->select('cb:game::'.$this->encodeState(['u' => $ownerId]), 'Choose a game', $options);
    }

    /**
     * Guards every step after the initial game select: the "u" state key
     * (set by gameSelect(), carried forward through every subsequent
     * custom_id/Modal) records who started this game. A state with no "u" —
     * shouldn't happen via the normal flow, but tolerated rather than
     * treated as unauthorized — is left unchecked.
     */
    private function assertOwner(array $state, string $userId): void
    {
        $owner = $state['u'] ?? null;

        if ($owner !== null && $owner !== $userId) {
            throw new DiscordInteractionUnauthorized;
        }
    }

    /**
     * Correctness/hints are never stored, only the raw picks: they're
     * recomputed against the day's target every time so there's a single
     * source of truth — mirrors CombleController::evaluateGuesses().
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

    private function cacheKey(string $userId, Carbon $day): string
    {
        return 'comble:discord:'.$userId.':'.$day->toDateString();
    }

    private function picks(string $userId, Carbon $day): array
    {
        return Cache::get($this->cacheKey($userId, $day), []);
    }

    private function appendPick(string $userId, Carbon $day, array $pick): void
    {
        $picks = array_slice([...$this->picks($userId, $day), $pick], 0, self::MAX_GUESSES);

        Cache::put($this->cacheKey($userId, $day), $picks, now()->addDays(self::CACHE_TTL_DAYS));
    }

    private function encodeState(array $state): string
    {
        return collect($state)
            ->reject(fn ($value) => $value === null)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode(';');
    }

    private function decodeState(?string $raw): array
    {
        $state = [];

        foreach (explode(';', (string) $raw) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);
            $state[$key] = $value;
        }

        return $state;
    }

    private function withState(array $state, string $key, ?string $value): array
    {
        if ($value === null) {
            unset($state[$key]);
        } else {
            $state[$key] = $value;
        }

        return $state;
    }

    private function select(string $customId, string $placeholder, array $options): array
    {
        return [
            'type' => 3,
            'custom_id' => $customId,
            'placeholder' => $placeholder,
            'options' => $options,
        ];
    }

    private function actionRow(array $components): array
    {
        return ['type' => 1, 'components' => $components];
    }
}
