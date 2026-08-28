<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameEntry;
use App\Models\GameResource;
use App\Models\ResourceValue;
use App\Models\User;
use App\Models\UserConnectedAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Drives the `/csk submit` wizard: game -> character -> listing type -> a
 * Modal for the combo's free-text details -> the game's required primary
 * resources -> a review step -> create. Only usable by a Discord user whose
 * account is linked to a site account (checked both when the wizard starts
 * and again right before the combo is actually created, since a stateless
 * Discord interaction gives no guarantee the link still exists by the time
 * the user reaches the end).
 *
 * Follows the same `key=value;key=value` custom_id state encoding as
 * DiscordComboWizard (`/csk browse`), under its own `sub:` prefix. One
 * difference from browse: browse's resources are optional filters (an "Any"
 * option, silently skipped when unset); here every primary resource is
 * required, mirroring the website's own combo form, which never leaves one
 * blank — a List/Duplicated resource silently defaults to its first
 * admin-ordered value, a Number resource defaults to 0, exactly as
 * StoreComboRequest/ComboController's create form already behaves. That
 * means the resource step never blocks progress on a missing value; it's
 * only offered so the user can override a default.
 *
 * The combo's free text (notation/damage/comments/video) can't be threaded
 * through custom_id like the short ids above — custom_id is capped at 100
 * characters, and notation alone can run to `combo`'s longText column width
 * — so once the details Modal is submitted it's stashed server-side in
 * Cache under a random token (state key `d`), the same "can't fit in
 * custom_id, so cache it" approach DiscordInteractionController's Comble
 * follow-up already uses, just for wizard state instead of a webhook token.
 */
class DiscordComboSubmit
{
    private const MAX_CHOICES = 25;

    private const ROWS_PER_PAGE = 4;

    private const MAX_MODAL_FIELDS = 5;

    private const CACHE_TTL_MINUTES = 15;

    public function __construct(private ComboSubmissionService $comboSubmission) {}

    public function start(string $discordUserId): array
    {
        return $this->resolveUser($discordUserId) ? $this->gameStep() : $this->notLinkedMessage();
    }

    /**
     * Handle a MESSAGE_COMPONENT interaction and return the next step's
     * response `data` object (type 7), except the controller must intercept
     * `sub:details:`/`sub:more:` before calling this and use buildModal()
     * (type 9) instead — those two open a Modal rather than editing the
     * message in place.
     */
    public function handleComponent(string $customId, array $values, string $discordUserId): array
    {
        [, $action, $extra, $stateRaw] = array_pad(explode(':', $customId, 4), 4, '');
        $state = $this->decodeState($stateRaw);
        $selected = $values[0] ?? null;

        return match ($action) {
            'game' => $this->characterStep($this->withState($state, 'g', $selected)),
            'char' => $this->typeStep($this->withState($state, 'c', $selected)),
            'ltype' => $this->detailsPromptStep($this->withState($state, 'l', $selected)),
            'res' => $this->resourceStep($this->withState($state, 'r'.$extra, $selected)),
            'page' => $this->resourceStep($this->withState($state, 'p', $extra)),
            'review' => $this->reviewStep($state),
            'confirm' => $this->confirm($state, $discordUserId),
            'cancel' => $this->cancelled($state),
            default => $this->gameStep(),
        };
    }

    /**
     * Build the Modal (interaction response type 9) for whichever button
     * triggered it — "Enter combo details" or "More details".
     */
    public function buildModal(string $customId): array
    {
        [, $action, , $stateRaw] = array_pad(explode(':', $customId, 4), 4, '');
        $state = $this->decodeState($stateRaw);

        return $action === 'more' ? $this->buildResourceModal($state) : $this->buildDetailsModal($state);
    }

    /**
     * Handle a MODAL_SUBMIT interaction and return the next step's response
     * `data` object (type 7).
     */
    public function handleModalSubmit(string $customId, array $submittedRows): array
    {
        [, $action, , $stateRaw] = array_pad(explode(':', $customId, 4), 4, '');
        $state = $this->decodeState($stateRaw);

        return $action === 'moresubmit'
            ? $this->handleResourceModalSubmit($state, $submittedRows)
            : $this->handleDetailsSubmit($state, $submittedRows);
    }

    private function gameStep(): array
    {
        $games = Game::orderBy('name')->limit(self::MAX_CHOICES)->get();

        $options = $games->map(fn (Game $game) => [
            'label' => Str::limit($game->name, 100, ''),
            'value' => (string) $game->idgame,
        ])->all();

        return [
            'embeds' => [['title' => 'Submit a combo', 'description' => 'Choose a game to begin.']],
            'components' => [
                $this->actionRow([$this->select('sub:game::', 'Choose a game', $options)]),
            ],
        ];
    }

    private function characterStep(array $state): array
    {
        $game = Game::find($state['g'] ?? null);

        if (! $game) {
            return $this->gameStep();
        }

        $characters = Character::where('game_idgame', $game->idgame)
            ->orderBy('name')
            ->limit(self::MAX_CHOICES)
            ->get();

        $options = $characters->map(fn (Character $character) => [
            'label' => Str::limit($character->name, 100, ''),
            'value' => (string) $character->idcharacter,
        ])->all();

        $stateRaw = $this->encodeState($state);

        return [
            'embeds' => [$this->summaryEmbed($state, $game)],
            'components' => [
                $this->actionRow([$this->select("sub:char::{$stateRaw}", 'Choose a character', $options)]),
            ],
        ];
    }

    private function typeStep(array $state): array
    {
        $game = Game::find($state['g'] ?? null);
        $character = Character::find($state['c'] ?? null);

        if (! $game || ! $character) {
            return $this->gameStep();
        }

        $types = GameEntry::where('gameid', $game->idgame)
            ->orderBy('order')
            ->orderBy('title')
            ->limit(self::MAX_CHOICES)
            ->get();

        $options = $types->map(fn (GameEntry $type) => [
            'label' => Str::limit($type->title, 100, ''),
            'value' => (string) $type->entryid,
        ])->all();

        $stateRaw = $this->encodeState($state);

        return [
            'embeds' => [$this->summaryEmbed($state, $game, $character)],
            'components' => [
                $this->actionRow([$this->select("sub:ltype::{$stateRaw}", 'Choose a listing type', $options)]),
            ],
        ];
    }

    private function detailsPromptStep(array $state): array
    {
        $game = Game::find($state['g'] ?? null);
        $character = Character::find($state['c'] ?? null);
        $listingType = GameEntry::find($state['l'] ?? null);

        if (! $game || ! $character || ! $listingType) {
            return $this->gameStep();
        }

        $stateRaw = $this->encodeState($state);

        return [
            'embeds' => [$this->summaryEmbed($state, $game, $character, $listingType)],
            'components' => [
                $this->actionRow([
                    ['type' => 2, 'style' => 1, 'label' => 'Enter combo details', 'custom_id' => "sub:details::{$stateRaw}"],
                    $this->resetButton(),
                ]),
            ],
        ];
    }

    private function buildDetailsModal(array $state): array
    {
        $stateRaw = $this->encodeState($state);

        return [
            'title' => 'Combo details',
            'custom_id' => "sub:detailsubmit::{$stateRaw}",
            'components' => [
                $this->actionRow([[
                    'type' => 4, 'custom_id' => 'combo', 'style' => 2, 'label' => 'Notation',
                    'required' => true, 'max_length' => 4000, 'placeholder' => '5A > 5B > 236B',
                ]]),
                $this->actionRow([[
                    'type' => 4, 'custom_id' => 'damage', 'style' => 1, 'label' => 'Damage (number, optional)',
                    'required' => false, 'placeholder' => 'e.g. 250',
                ]]),
                $this->actionRow([[
                    'type' => 4, 'custom_id' => 'comments', 'style' => 2, 'label' => 'Comments (optional)',
                    'required' => false,
                ]]),
                $this->actionRow([[
                    'type' => 4, 'custom_id' => 'video', 'style' => 1, 'label' => 'Video URL (optional)',
                    'required' => false, 'max_length' => 255, 'placeholder' => 'YouTube/Twitter/Streamable link',
                ]]),
            ],
        ];
    }

    /**
     * On failure, re-renders the details prompt with an inline error rather
     * than reopening the Modal — a Modal response can't carry the
     * previously-typed values back in without risking the custom_id length
     * cap, so a rejected submission asks the user to open a fresh one.
     */
    private function handleDetailsSubmit(array $state, array $submittedRows): array
    {
        $submitted = collect($submittedRows)
            ->pluck('components.0')
            ->filter()
            ->mapWithKeys(fn ($input) => [$input['custom_id'] => trim((string) ($input['value'] ?? ''))]);

        $combo = $submitted->get('combo', '');
        $damage = $submitted->get('damage', '');
        $comments = $submitted->get('comments', '');
        $video = $submitted->get('video', '');

        $errors = [];

        if ($combo === '') {
            $errors[] = 'Combo notation is required.';
        }

        if ($damage !== '' && (! is_numeric($damage) || (float) $damage < 0)) {
            $errors[] = 'Damage must be a non-negative number.';
        }

        if ($errors) {
            $step = $this->detailsPromptStep($state);
            $step['embeds'][0]['description'] .= "\n\n⚠ ".implode(' ', $errors);

            return $step;
        }

        $token = Str::random(24);

        Cache::put($this->detailsCacheKey($token), [
            'combo' => $combo,
            'damage' => $damage,
            'comments' => $comments,
            'video' => $video,
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $this->resourceStep($this->withState($state, 'd', $token));
    }

    private function resourceStep(array $state): array
    {
        $game = Game::find($state['g'] ?? null);
        $character = Character::find($state['c'] ?? null);
        $listingType = GameEntry::find($state['l'] ?? null);

        if (! $game || ! $character || ! $listingType) {
            return $this->gameStep();
        }

        $dropdownResources = $this->dropdownResources($game);
        $modalResources = $this->modalResources($game);

        if ($dropdownResources->isEmpty() && $modalResources->isEmpty()) {
            return $this->reviewStep($state);
        }

        // A type-3 (Duplicated) resource needs two rows — a 1st-pick and a
        // 2nd-pick — since it matches on two values; flattening to one
        // row-builder per row lets pagination slice whole rows.
        $rowBuilders = $dropdownResources->flatMap(function (GameResource $resource) use ($state) {
            return $resource->type === 1
                ? [fn () => $this->resourceSelectRow($resource, 'r'.$resource->idgame_resources, $state, $resource->text_name)]
                : [
                    fn () => $this->resourceSelectRow($resource, 'r'.$resource->idgame_resources.'a', $state, $resource->text_name.' (1st)'),
                    fn () => $this->resourceSelectRow($resource, 'r'.$resource->idgame_resources.'b', $state, $resource->text_name.' (2nd)'),
                ];
        });

        $totalPages = max(1, (int) ceil($rowBuilders->count() / self::ROWS_PER_PAGE));
        $page = max(0, min($totalPages - 1, (int) ($state['p'] ?? 0)));

        $rows = $rowBuilders
            ->slice($page * self::ROWS_PER_PAGE, self::ROWS_PER_PAGE)
            ->values()
            ->map(fn (callable $build) => $build())
            ->all();

        $buttons = [];

        if ($totalPages > 1) {
            $buttons[] = $this->pageButton('Previous', $page - 1, $state, disabled: $page === 0);
            $buttons[] = $this->pageButton('Next', $page + 1, $state, disabled: $page === $totalPages - 1);
        }

        $buttons[] = $this->reviewButton($state);
        $buttons[] = $this->resetButton();

        if ($modalResources->isNotEmpty()) {
            $buttons[] = ['type' => 2, 'style' => 2, 'label' => 'More details', 'custom_id' => 'sub:more::'.$this->encodeState($state)];
        }

        $rows[] = $this->actionRow($buttons);

        return [
            'embeds' => [$this->summaryEmbed($state, $game, $character, $listingType, $totalPages > 1 ? $page + 1 : null, $totalPages)],
            'components' => $rows,
        ];
    }

    private function resourceSelectRow(GameResource $resource, string $stateKey, array $state, string $label): array
    {
        $selectedValue = $state[$stateKey] ?? null;
        $firstValueId = optional($resource->values->first())->idResources_values;

        $options = [];

        foreach ($resource->values->take(self::MAX_CHOICES) as $value) {
            $isDefault = $selectedValue !== null
                ? $selectedValue === (string) $value->idResources_values
                : $value->idResources_values === $firstValueId;

            $options[] = [
                'label' => Str::limit((string) $value->value, 100, ''),
                'value' => (string) $value->idResources_values,
                'default' => $isDefault,
            ];
        }

        $extra = str_starts_with($stateKey, 'r') ? substr($stateKey, 1) : $stateKey;
        $withoutThis = collect($state)->except($stateKey)->all();
        $stateRaw = $this->encodeState($withoutThis);

        return $this->actionRow([
            $this->select("sub:res:{$extra}:{$stateRaw}", Str::limit($label, 100, ''), $options),
        ]);
    }

    private function buildResourceModal(array $state): array
    {
        $game = Game::find($state['g'] ?? null);
        $resources = $game ? $this->modalResources($game) : collect();

        $fieldBuilders = $resources->flatMap(function (GameResource $resource) use ($state) {
            if ($resource->type === 2) {
                $key = 'f'.$resource->idgame_resources;

                return [fn () => $this->numberField($key, $resource->text_name, (string) ($state[$key] ?? '0'))];
            }

            $keyA = 'f'.$resource->idgame_resources.'a';
            $keyB = 'f'.$resource->idgame_resources.'b';
            $default = optional($resource->values->first())->value ?? '';

            return [
                fn () => $this->textField($keyA, $resource->text_name.' (1st, contains)', (string) ($state[$keyA] ?? $default)),
                fn () => $this->textField($keyB, $resource->text_name.' (2nd, contains)', (string) ($state[$keyB] ?? $default)),
            ];
        });

        $components = $fieldBuilders->take(self::MAX_MODAL_FIELDS)->map(fn (callable $build) => $build())->values()->all();

        return [
            'title' => 'More details',
            'custom_id' => 'sub:moresubmit::'.$this->encodeState($state),
            'components' => $components,
        ];
    }

    private function numberField(string $key, string $label, string $value): array
    {
        return $this->actionRow([[
            'type' => 4, 'custom_id' => $key, 'style' => 1,
            'label' => Str::limit("{$label} (number)", 45, ''),
            'required' => true, 'value' => $value, 'placeholder' => 'Number, e.g. 3',
        ]]);
    }

    private function textField(string $key, string $label, string $value): array
    {
        return $this->actionRow([[
            'type' => 4, 'custom_id' => $key, 'style' => 1,
            'label' => Str::limit($label, 45, ''),
            'required' => true, 'value' => Str::limit($value, 100, ''), 'placeholder' => 'Type the value name',
        ]]);
    }

    /**
     * A required numeric field left untouched keeps its pre-filled '0'
     * (matching the website's own default); a required text field whose
     * typed value doesn't match anything keeps its previous state (which
     * falls back to the game's first ordered value at resolveResources()
     * time) rather than blocking the wizard on an unmatched search term.
     */
    private function handleResourceModalSubmit(array $state, array $submittedRows): array
    {
        $game = Game::find($state['g'] ?? null);

        if (! $game) {
            return $this->gameStep();
        }

        $resources = $this->modalResources($game)->keyBy('idgame_resources');

        $submitted = collect($submittedRows)
            ->pluck('components.0')
            ->filter()
            ->mapWithKeys(fn ($input) => [$input['custom_id'] => trim((string) ($input['value'] ?? ''))]);

        foreach ($submitted as $field => $value) {
            if (preg_match('/^f(\d+)([ab])?$/', $field, $matches) !== 1) {
                continue;
            }

            $resource = $resources->get((int) $matches[1]);

            if (! $resource) {
                continue;
            }

            if ($resource->type === 2) {
                $state = (is_numeric($value) && (float) $value >= 0)
                    ? $this->withState($state, $field, $value)
                    : $this->withState($state, $field, null);

                continue;
            }

            if ($value === '') {
                continue;
            }

            $match = $resource->values->first(fn ($v) => stripos($v->value, $value) !== false);

            if ($match) {
                $state = $this->withState($state, $field, (string) $match->idResources_values);
            }
        }

        return $this->resourceStep($state);
    }

    private function reviewStep(array $state): array
    {
        $game = Game::find($state['g'] ?? null);
        $character = Character::find($state['c'] ?? null);
        $listingType = GameEntry::find($state['l'] ?? null);

        if (! $game || ! $character || ! $listingType) {
            return $this->gameStep();
        }

        $details = Cache::get($this->detailsCacheKey($state['d'] ?? ''));

        if (! $details) {
            return $this->expiredMessage();
        }

        $resources = $this->resolveResources($game, $state);

        $lines = [
            "**Game:** {$game->name}",
            "**Character:** {$character->name}",
            "**Type:** {$listingType->title}",
            "**Notation:** {$details['combo']}",
        ];

        if ($details['damage'] !== '') {
            $lines[] = "**Damage:** {$details['damage']}";
        }

        if ($details['video'] !== '') {
            $lines[] = "**Video:** {$details['video']}";
        }

        if ($details['comments'] !== '') {
            $lines[] = "**Comments:** {$details['comments']}";
        }

        foreach ($this->primaryResources($game) as $resource) {
            $lines[] = "**{$resource->text_name}:** ".$this->describeResourceValue($resource, $resources[$resource->idgame_resources]);
        }

        $stateRaw = $this->encodeState($state);

        return [
            'embeds' => [['title' => 'Review your submission', 'description' => implode("\n", $lines)]],
            'components' => [
                $this->actionRow([
                    ['type' => 2, 'style' => 3, 'label' => 'Submit combo', 'custom_id' => "sub:confirm::{$stateRaw}"],
                    ['type' => 2, 'style' => 4, 'label' => 'Cancel', 'custom_id' => 'sub:cancel::'],
                ]),
            ],
        ];
    }

    private function confirm(array $state, string $discordUserId): array
    {
        $user = $this->resolveUser($discordUserId);

        if (! $user) {
            return $this->notLinkedMessage();
        }

        $game = Game::find($state['g'] ?? null);
        $character = Character::find($state['c'] ?? null);
        $listingType = GameEntry::find($state['l'] ?? null);

        if (! $game || ! $character || ! $listingType) {
            return $this->gameStep();
        }

        $details = Cache::get($this->detailsCacheKey($state['d'] ?? ''));

        if (! $details) {
            return $this->expiredMessage();
        }

        $resources = $this->resolveResources($game, $state);

        $combo = $this->comboSubmission->create($game, [
            'combo' => $details['combo'],
            'comments' => $details['comments'] !== '' ? $details['comments'] : null,
            'video' => $details['video'] !== '' ? $details['video'] : null,
            'character_idcharacter' => $character->idcharacter,
            'damage' => $details['damage'] !== '' ? $details['damage'] : null,
            'type' => $listingType->entryid,
        ], $resources, $user->iduser);

        Cache::forget($this->detailsCacheKey($state['d']));

        $url = rtrim(config('app.url'), '/').route('combos.show', $combo, absolute: false);

        return [
            'content' => "Combo submitted! It'll appear once it's verified: {$url}",
            'embeds' => [],
            'components' => [],
        ];
    }

    private function cancelled(array $state): array
    {
        if (! empty($state['d'])) {
            Cache::forget($this->detailsCacheKey($state['d']));
        }

        return ['content' => 'Cancelled.', 'embeds' => [], 'components' => []];
    }

    private function notLinkedMessage(): array
    {
        $url = rtrim(config('app.url'), '/').route('connections.edit', absolute: false);

        return [
            'content' => "You need to connect your Discord account to Combo好き before submitting a combo. Head to {$url} and connect it there, then run `/csk submit` again.",
        ];
    }

    private function expiredMessage(): array
    {
        return [
            'content' => 'Your combo details expired. Please run `/csk submit` again.',
            'embeds' => [],
            'components' => [],
        ];
    }

    private function resolveUser(string $discordUserId): ?User
    {
        if ($discordUserId === '') {
            return null;
        }

        return UserConnectedAccount::where('provider', DiscordAccountLinker::PROVIDER)
            ->where('provider_user_id', $discordUserId)
            ->first()?->user;
    }

    /**
     * Resolves every primary resource to a definite value, defaulting an
     * untouched List/Duplicated resource to its first admin-ordered value
     * and an untouched Number resource to 0 — mirroring the website combo
     * form, which never leaves a primary resource blank either.
     *
     * @return array<int, mixed> Keyed by GameResource id, same shape ComboSubmissionService::syncResources() expects.
     */
    private function resolveResources(Game $game, array $state): array
    {
        $resources = [];

        foreach ($this->primaryResources($game) as $resource) {
            $id = $resource->idgame_resources;
            $firstValueId = optional($resource->values->first())->idResources_values;

            if ($resource->type === 1) {
                $resources[$id] = $state['r'.$id] ?? $firstValueId;

                continue;
            }

            if ($resource->type === 2) {
                $resources[$id] = $state['f'.$id] ?? '0';

                continue;
            }

            $resources[$id] = $this->isDropdownEligible($resource)
                ? [$state['r'.$id.'a'] ?? $firstValueId, $state['r'.$id.'b'] ?? $firstValueId]
                : [$state['f'.$id.'a'] ?? $firstValueId, $state['f'.$id.'b'] ?? $firstValueId];
        }

        return $resources;
    }

    private function describeResourceValue(GameResource $resource, mixed $value): string
    {
        if ($resource->type === 2) {
            return (string) $value;
        }

        if (is_array($value)) {
            return collect($value)->map(fn ($id) => ResourceValue::find($id)?->value ?? 'Unknown')->implode(', ');
        }

        return ResourceValue::find($value)?->value ?? 'Unknown';
    }

    /** Primary resources renderable as select menus: type 1, or type 3 with a short enough value list. */
    private function dropdownResources(Game $game): Collection
    {
        return $this->primaryResources($game)->filter(fn (GameResource $r) => $this->isDropdownEligible($r))->values();
    }

    /** Primary resources that need the "More details" modal instead: type 2, or type 3 with too many values for a dropdown. */
    private function modalResources(Game $game): Collection
    {
        return $this->primaryResources($game)->reject(fn (GameResource $r) => $this->isDropdownEligible($r))->values();
    }

    private function primaryResources(Game $game): Collection
    {
        return GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->whereIn('type', [1, 2, 3])
            ->with('values')
            ->orderBy('text_name')
            ->get();
    }

    private function isDropdownEligible(GameResource $resource): bool
    {
        return $resource->type === 1 || ($resource->type === 3 && $resource->values->count() <= self::MAX_CHOICES);
    }

    private function detailsCacheKey(string $token): string
    {
        return 'discord:submit:details:'.$token;
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

    private function reviewButton(array $state): array
    {
        return ['type' => 2, 'style' => 3, 'label' => 'Review & submit', 'custom_id' => 'sub:review::'.$this->encodeState($state)];
    }

    private function resetButton(): array
    {
        return ['type' => 2, 'style' => 2, 'label' => 'Start over', 'custom_id' => 'sub:reset::'];
    }

    private function pageButton(string $label, int $targetPage, array $state, bool $disabled): array
    {
        return [
            'type' => 2,
            'style' => 2,
            'label' => $label,
            'custom_id' => 'sub:page:'.$targetPage.':'.$this->encodeState($state),
            'disabled' => $disabled,
        ];
    }

    private function summaryEmbed(
        array $state,
        Game $game,
        ?Character $character = null,
        ?GameEntry $listingType = null,
        ?int $page = null,
        ?int $totalPages = null,
    ): array {
        $lines = ["**Game:** {$game->name}"];

        if ($character) {
            $lines[] = "**Character:** {$character->name}";
        }

        if ($listingType) {
            $lines[] = "**Type:** {$listingType->title}";
        }

        if ($page !== null && $totalPages !== null) {
            $lines[] = "*Required fields — page {$page} of {$totalPages}*";
        }

        return ['title' => 'Submit a combo', 'description' => implode("\n", $lines)];
    }
}
