<?php

namespace App\Services;

use App\Models\Character;
use App\Models\Game;
use App\Models\GameResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Drives the `/csk browse` dropdown flow: game -> character -> the game's
 * primary resource filters -> results, each step re-rendering the same
 * (ephemeral) message via a Discord MESSAGE_COMPONENT (or MODAL_SUBMIT)
 * interaction.
 *
 * Discord interactions are stateless HTTP calls with no session between
 * clicks, so the accumulated selection is threaded through each
 * component's `custom_id` as a compact `key=value;key=value` string (see
 * encodeState/decodeState) — custom_id is capped at 100 characters, hence
 * the short keys.
 *
 * Every primary resource gets a filter, but not every resource type can be
 * a dropdown:
 *  - type 1 (fixed list) and type 3 (paired/duplicated) with a small value
 *    list are rendered as select menus, one row each (type 3 needs two —
 *    a 1st-pick and 2nd-pick — since it matches on up to two values). These
 *    are paginated (see RESOURCES_PER_PAGE) since a message can only fit
 *    so many action rows at once.
 *  - type 2 (numeric comparison) and type 3 with too many values for a
 *    dropdown (Discord caps a select at 25 options) go in a "More filters"
 *    Modal instead, as free-text fields — numeric fields filter with `<=`,
 *    text fields resolve to the first value whose name *contains* what was
 *    typed, mirroring the site's own text-search filters rather than
 *    requiring an exact match.
 */
class DiscordComboWizard
{
    /**
     * Discord allows at most 5 action rows per message; one row is reserved
     * for the Search/Start over/Previous/Next/More filters buttons, leaving
     * 4 for resource dropdowns.
     */
    private const ROWS_PER_PAGE = 4;

    /**
     * Discord select menus cap at 25 options; one slot is reserved for the
     * leading "Any" option. A type-3 resource with more values than this
     * can't be a dropdown at all (see isDropdownEligible()) and goes in the
     * "More filters" modal as a text search instead.
     */
    private const MAX_CHOICES = 24;

    /**
     * Discord modals cap at 5 text input rows.
     */
    private const MAX_MODAL_FIELDS = 5;

    private const ANY = '_any_';

    public function __construct(private DiscordComboSearch $comboSearch) {}

    public function start(): array
    {
        return $this->gameStep();
    }

    /**
     * Handle a MESSAGE_COMPONENT interaction (a click on one of the
     * wizard's own dropdowns/buttons) and return the next step's response
     * `data` object, meant to be sent back as an UPDATE_MESSAGE (type 7) —
     * except for 'more', which the controller must send back as a MODAL
     * (type 9) using buildModal() instead of this method's result.
     */
    public function handleComponent(string $customId, array $values, ?string $channelId): array
    {
        [, $action, $extra, $stateRaw] = array_pad(explode(':', $customId, 4), 4, '');
        $state = $this->decodeState($stateRaw);
        $selected = $values[0] ?? null;

        return match ($action) {
            'game' => $this->characterStep($this->withState($state, 'g', $selected)),
            'char' => $this->resourceStep($this->withState($state, 'c', $selected === self::ANY ? null : $selected)),
            'res' => $this->resourceStep($this->withState($state, 'r'.$extra, $selected === self::ANY ? null : $selected)),
            'page' => $this->resourceStep($this->withState($state, 'p', $extra)),
            'search' => $this->runSearch($state, $channelId),
            default => $this->gameStep(),
        };
    }

    /**
     * Build the "More filters" Modal (interaction response type 9) for the
     * numeric/large-list primary resources that can't be dropdowns.
     */
    public function buildModal(string $customId): array
    {
        [, , , $stateRaw] = array_pad(explode(':', $customId, 4), 4, '');
        $state = $this->decodeState($stateRaw);
        $game = Game::find($state['g'] ?? null);

        $fields = $game ? $this->modalResources($game)->take(self::MAX_MODAL_FIELDS) : collect();

        $components = $fields->map(function (GameResource $resource) use ($state) {
            $field = 'f'.$resource->idgame_resources;

            return $this->actionRow([[
                'type' => 4,
                'custom_id' => $field,
                'style' => 1,
                'label' => Str::limit($this->modalFieldLabel($resource), 45, ''),
                'required' => false,
                'value' => (string) ($state[$field] ?? ''),
                'placeholder' => $resource->type === 2 ? 'Number, e.g. 3' : 'Type part of the name',
            ]]);
        })->all();

        return [
            'title' => 'More filters',
            'custom_id' => 'w:modalsubmit::'.$this->encodeState($state),
            'components' => $components,
        ];
    }

    /**
     * Handle a MODAL_SUBMIT interaction (the "More filters" form being
     * submitted) and return the next step's response `data` object, meant
     * to be sent back as an UPDATE_MESSAGE (type 7).
     */
    public function handleModalSubmit(string $customId, array $submittedRows, ?string $channelId): array
    {
        [, , , $stateRaw] = array_pad(explode(':', $customId, 4), 4, '');
        $state = $this->decodeState($stateRaw);
        $game = Game::find($state['g'] ?? null);

        if (! $game) {
            return $this->gameStep();
        }

        // Modal submissions nest one TEXT_INPUT per action row.
        $submitted = collect($submittedRows)
            ->pluck('components.0')
            ->filter()
            ->mapWithKeys(fn ($input) => [$input['custom_id'] => trim((string) ($input['value'] ?? ''))]);

        $resources = $this->modalResources($game)->keyBy('idgame_resources');

        foreach ($submitted as $field => $value) {
            $resource = $resources->get((int) substr($field, 1));

            if (! $resource) {
                continue;
            }

            if ($value === '') {
                $state = $this->withState($state, $field, null);

                continue;
            }

            if ($resource->type === 2) {
                $state = $this->withState($state, $field, $value);

                continue;
            }

            // Large type-3 resource: resolve the typed text to the first
            // matching value by substring, mirroring the site's own
            // "contains" search rather than requiring an exact name.
            $match = $resource->values->first(fn ($v) => stripos($v->value, $value) !== false);
            $state = $this->withState($state, $field, $match ? (string) $match->idResources_values : null);
        }

        return $this->resourceStep($state);
    }

    private function gameStep(): array
    {
        $games = Game::orderBy('name')->limit(self::MAX_CHOICES)->get();

        $options = $games->map(fn (Game $game) => [
            'label' => Str::limit($game->name, 100, ''),
            'value' => (string) $game->idgame,
        ])->all();

        return [
            'embeds' => [['title' => 'Search combos', 'description' => 'Choose a game to begin.']],
            'components' => [
                $this->actionRow([$this->select('w:game::', 'Choose a game', $options)]),
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

        $options = [['label' => 'Any character', 'value' => self::ANY, 'default' => ($state['c'] ?? null) === null]];

        foreach ($characters as $character) {
            $options[] = [
                'label' => Str::limit($character->name, 100, ''),
                'value' => (string) $character->idcharacter,
                'default' => ($state['c'] ?? null) === (string) $character->idcharacter,
            ];
        }

        $stateRaw = $this->encodeState($state);

        return [
            'embeds' => [$this->summaryEmbed($state, $game)],
            'components' => [
                $this->actionRow([$this->select("w:char::{$stateRaw}", 'Choose a character (optional)', $options)]),
                $this->actionRow([$this->searchButton($state), $this->resetButton()]),
            ],
        ];
    }

    private function resourceStep(array $state): array
    {
        $game = Game::find($state['g'] ?? null);

        if (! $game) {
            return $this->gameStep();
        }

        $dropdownResources = $this->dropdownResources($game);
        $modalResources = $this->modalResources($game);

        // Flatten to one row-builder per dropdown row (a type-1 resource
        // needs one row; a type-3 resource needs two, a 1st-pick and a
        // 2nd-pick, since it matches on up to two values) so pagination
        // slices whole rows rather than resources.
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

        // ->values() re-indexes from 0: slice() on a Collection preserves
        // original keys, and a PHP array with gapped integer keys (e.g.
        // {4,5,6} on page 2) serializes to a JSON *object*, not an array,
        // which Discord's API rejects for `components`.
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

        $buttons[] = $this->searchButton($state);
        $buttons[] = $this->resetButton();

        if ($modalResources->isNotEmpty()) {
            $buttons[] = ['type' => 2, 'style' => 2, 'label' => 'More filters', 'custom_id' => 'w:more::'.$this->encodeState($state)];
        }

        $rows[] = $this->actionRow($buttons);

        return [
            'embeds' => [$this->summaryEmbed($state, $game, $totalPages > 1 ? $page + 1 : null, $totalPages)],
            'components' => $rows,
        ];
    }

    private function resourceSelectRow(GameResource $resource, string $stateKey, array $state, string $label): array
    {
        $selectedValue = $state[$stateKey] ?? null;

        $options = [['label' => 'Any', 'value' => self::ANY, 'default' => $selectedValue === null]];

        foreach ($resource->values->take(self::MAX_CHOICES) as $value) {
            $options[] = [
                'label' => Str::limit((string) $value->value, 100, ''),
                'value' => (string) $value->idResources_values,
                'default' => $selectedValue === (string) $value->idResources_values,
            ];
        }

        // This field's own answer arrives via the interaction's `values`,
        // not the custom_id — so the id only needs to carry every OTHER
        // already-accumulated selection (including the current page)
        // forward. The state key (e.g. "5" or "5a") becomes the custom_id's
        // `extra` segment, which handleComponent() turns back into the same
        // key by prefixing it with "r".
        $extra = str_starts_with($stateKey, 'r') ? substr($stateKey, 1) : $stateKey;
        $withoutThis = collect($state)->except($stateKey)->all();
        $stateRaw = $this->encodeState($withoutThis);

        return $this->actionRow([
            $this->select("w:res:{$extra}:{$stateRaw}", Str::limit($label, 100, ''), $options),
        ]);
    }

    private function runSearch(array $state, ?string $channelId): array
    {
        $game = Game::find($state['g'] ?? null);

        if (! $game) {
            return $this->gameStep();
        }

        $filters = $this->buildFilters($game, $state);

        $result = $this->comboSearch->runSearch($game, $filters, $channelId);

        // runSearch()'s "no combos found" fallback is a `content` message
        // with no components; the wizard message would otherwise be left
        // showing stale dropdowns with no way to try again. `flags` is
        // dropped since the message's ephemeral status is already fixed
        // from the wizard's initial response and can't be changed here.
        if (! isset($result['embeds'])) {
            unset($result['flags']);
            $result['components'] = [$this->actionRow([$this->resetButton()])];
        }

        return $result;
    }

    private function buildFilters(Game $game, array $state): array
    {
        $resources = GameResource::where('game_idgame', $game->idgame)
            ->where('primaryORsecundary', 1)
            ->with('values')
            ->get();

        $filters = ['characterid' => $state['c'] ?? null];

        foreach ($resources as $resource) {
            $field = str_replace(' ', '_', $resource->text_name);
            $id = $resource->idgame_resources;

            if ($resource->type === 1) {
                $filters[$field] = $state["r{$id}"] ?? null;
            } elseif ($resource->type === 2) {
                $filters[$field] = $state["f{$id}"] ?? null;
            } elseif ($resource->type === 3) {
                $filters[$field] = $this->isDropdownEligible($resource)
                    ? array_values(array_filter([$state["r{$id}a"] ?? null, $state["r{$id}b"] ?? null]))
                    : ($state["f{$id}"] ?? null);
            }
        }

        return $filters;
    }

    /** Primary resources renderable as select menus: type 1, or type 3 with a short enough value list. */
    private function dropdownResources(Game $game): Collection
    {
        return $this->primaryResources($game)->filter(fn (GameResource $r) => $this->isDropdownEligible($r))->values();
    }

    /** Primary resources that need the "More filters" modal instead: type 2, or type 3 with too many values for a dropdown. */
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

    private function modalFieldLabel(GameResource $resource): string
    {
        return $resource->type === 2
            ? "{$resource->text_name} (number, max)"
            : "{$resource->text_name} (contains)";
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

    private function searchButton(array $state): array
    {
        return [
            'type' => 2,
            'style' => 3,
            'label' => 'Search',
            'custom_id' => 'w:search::'.$this->encodeState($state),
        ];
    }

    private function resetButton(): array
    {
        return ['type' => 2, 'style' => 2, 'label' => 'Start over', 'custom_id' => 'w:reset::'];
    }

    private function pageButton(string $label, int $targetPage, array $state, bool $disabled): array
    {
        return [
            'type' => 2,
            'style' => 2,
            'label' => $label,
            'custom_id' => 'w:page:'.$targetPage.':'.$this->encodeState($state),
            'disabled' => $disabled,
        ];
    }

    private function summaryEmbed(array $state, Game $game, ?int $page = null, ?int $totalPages = null): array
    {
        $lines = ["**Game:** {$game->name}"];

        if (! empty($state['c'])) {
            $character = Character::find($state['c']);
            $lines[] = '**Character:** '.($character->name ?? 'Unknown');
        }

        if ($page !== null && $totalPages !== null) {
            $lines[] = "*Resource filters — page {$page} of {$totalPages}*";
        }

        return ['title' => 'Search combos', 'description' => implode("\n", $lines)];
    }
}
