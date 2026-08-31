<?php

namespace App\Services;

use App\Models\Game;
use App\Models\ListCategory;
use App\Models\ListModel;
use App\Models\ListPage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Drives the `/csk guide-browse` dropdown flow: guide -> page -> category ->
 * combos, each step re-rendering the same public message via a Discord
 * MESSAGE_COMPONENT interaction (see DiscordComboWizard for the general
 * pattern this follows: interactions are stateless, so the accumulated
 * selection is threaded through each component's `custom_id` as a compact
 * `key=value;key=value` string via encodeState/decodeState).
 *
 * Unlike DiscordComboWizard's ephemeral responses or DiscordCombleGame's
 * ownership-guarded public ones, this message is public with no click
 * restriction at all — anyone in the channel can drive the same dropdowns,
 * which is the point: browsing a guide together.
 */
class DiscordGuideBrowse
{
    /** Discord select menus cap at 25 options; one slot is reserved for the leading "All ..." option. */
    private const MAX_CHOICES = 24;

    private const ANY = '_any_';

    private const RESULTS_PER_PAGE = 8;

    public function start(array $interactionData): array
    {
        $options = $this->flattenOptions($interactionData['options'] ?? []);
        $query = $this->baseGuidesQuery();

        if (! empty($options['game'])) {
            $game = $this->resolveGame($options['game']);

            if (! $game) {
                return $this->ephemeral("No game found matching \"{$options['game']}\".");
            }

            $query->where('game_idgame', $game->idgame);
        }

        if (! empty($options['name'])) {
            $query->where('list_name', 'like', '%'.$options['name'].'%');
        }

        $guides = $query->get();

        if ($guides->isEmpty()) {
            return $this->ephemeral('No guides found.');
        }

        if ($guides->count() === 1) {
            return $this->pageStep(['l' => (string) $guides->first()->idlist]);
        }

        return $this->guideStep($guides);
    }

    /**
     * Handle a MESSAGE_COMPONENT interaction (a click on one of this
     * command's own dropdowns/buttons) and return the next step's response
     * `data` object, meant to be sent back as an UPDATE_MESSAGE (type 7).
     */
    public function handleComponent(string $customId, array $values): array
    {
        [, $action, $extra, $stateRaw] = array_pad(explode(':', $customId, 4), 4, '');
        $state = $this->decodeState($stateRaw);
        $selected = $values[0] ?? null;

        return match ($action) {
            'guide' => $this->pageStep($this->withState($state, 'l', $selected)),
            'page' => $this->categoryStep($this->withState($state, 'p', $selected === self::ANY ? null : $selected)),
            'cat' => $this->resultsStep($this->withState(
                $this->withState($state, 'c', $selected === self::ANY ? null : $selected),
                'rp',
                null
            )),
            'respage' => $this->resultsStep($this->withState($state, 'rp', $extra)),
            'changecat' => $this->categoryStep($this->withoutKeys($state, ['c', 'rp'])),
            'changepage' => $this->pageStep($this->withoutKeys($state, ['p', 'c', 'rp'])),
            default => $this->guideStep($this->baseGuidesQuery()->get()),
        };
    }

    private function baseGuidesQuery()
    {
        return ListModel::where('type', '!=', 0)
            ->where('is_favorite_guide', false)
            ->orderByDesc('views')
            ->orderByDesc('idlist')
            ->limit(self::MAX_CHOICES);
    }

    private function guideStep(Collection $guides): array
    {
        if ($guides->isEmpty()) {
            return $this->ephemeral('No guides found.');
        }

        $options = $guides->map(fn (ListModel $guide) => [
            'label' => Str::limit($guide->list_name, 100, ''),
            'value' => (string) $guide->idlist,
        ])->all();

        return [
            'embeds' => [['title' => 'Browse a guide', 'description' => 'Choose a guide to begin.']],
            'components' => [
                $this->actionRow([$this->select('gb:guide::', 'Choose a guide', $options)]),
            ],
        ];
    }

    private function pageStep(array $state): array
    {
        $guide = ListModel::with('game')->find($state['l'] ?? null);

        if (! $guide) {
            return $this->guideStep($this->baseGuidesQuery()->get());
        }

        $pages = ListPage::where('idList', $guide->idlist)->orderBy('order')->orderBy('Title')->get();

        if ($pages->isEmpty()) {
            return $this->categoryStep($state);
        }

        $options = [['label' => 'All pages', 'value' => self::ANY, 'default' => true]];

        foreach ($pages->take(self::MAX_CHOICES) as $page) {
            $options[] = ['label' => Str::limit($page->Title, 100, ''), 'value' => (string) $page->idListPage];
        }

        $stateRaw = $this->encodeState($state);

        return [
            'embeds' => [$this->summaryEmbed($guide, 'Choose a page.')],
            'components' => [
                $this->actionRow([$this->select("gb:page::{$stateRaw}", 'Choose a page', $options)]),
            ],
        ];
    }

    private function categoryStep(array $state): array
    {
        $guide = ListModel::with('game')->find($state['l'] ?? null);

        if (! $guide) {
            return $this->guideStep($this->baseGuidesQuery()->get());
        }

        $pageId = isset($state['p']) ? (int) $state['p'] : null;
        $categories = $this->scopedCategories($guide, $pageId);
        $combos = $this->pageScopedCombos($guide, $pageId, $categories);
        $hasUncategorized = $combos->contains(fn ($combo) => $combo->pivot->list_category_idlist_category === null);

        if ($categories->isEmpty() && ! $hasUncategorized) {
            return $this->resultsStep($state);
        }

        $options = [['label' => 'All categories', 'value' => self::ANY, 'default' => true]];

        foreach ($categories->take(self::MAX_CHOICES) as $category) {
            $options[] = ['label' => Str::limit($category->title, 100, ''), 'value' => (string) $category->idlist_category];
        }

        if ($hasUncategorized && count($options) <= self::MAX_CHOICES) {
            $options[] = ['label' => 'Uncategorized', 'value' => '0'];
        }

        $stateRaw = $this->encodeState($state);

        return [
            'embeds' => [$this->summaryEmbed($guide, 'Choose a category.', $pageId)],
            'components' => [
                $this->actionRow([$this->select("gb:cat::{$stateRaw}", 'Choose a category', $options)]),
            ],
        ];
    }

    private function resultsStep(array $state): array
    {
        $guide = ListModel::with('game')->find($state['l'] ?? null);

        if (! $guide) {
            return $this->guideStep($this->baseGuidesQuery()->get());
        }

        $pageId = isset($state['p']) ? (int) $state['p'] : null;
        $categories = $this->scopedCategories($guide, $pageId);
        $combos = $this->pageScopedCombos($guide, $pageId, $categories);

        $categoryFilter = $state['c'] ?? null;

        if ($categoryFilter === '0') {
            $combos = $combos->filter(fn ($combo) => $combo->pivot->list_category_idlist_category === null)->values();
        } elseif ($categoryFilter !== null) {
            $combos = $combos->filter(
                fn ($combo) => (string) $combo->pivot->list_category_idlist_category === (string) $categoryFilter
            )->values();
        }

        $totalPages = max(1, (int) ceil($combos->count() / self::RESULTS_PER_PAGE));
        $page = max(0, min($totalPages - 1, (int) ($state['rp'] ?? 0)));
        $pageCombos = $combos->slice($page * self::RESULTS_PER_PAGE, self::RESULTS_PER_PAGE)->values();

        $lines = $pageCombos->isEmpty()
            ? ['No combos found for this selection.']
            : $pageCombos->map(function ($combo, $i) use ($page) {
                $number = $page * self::RESULTS_PER_PAGE + $i + 1;
                $damage = $combo->damage !== null ? number_format((float) $combo->damage, 0, '', '.').' dmg' : 'unknown dmg';

                return "{$number}. **{$combo->character->name}** `".Str::limit($combo->combo, 80, '')."` — {$damage}";
            })->all();

        if ($totalPages > 1) {
            $lines[] = '';
            $lines[] = '*Page '.($page + 1)." of {$totalPages}*";
        }

        $stateRaw = $this->encodeState($state);

        $buttons = [];

        if ($totalPages > 1) {
            $buttons[] = $this->navButton('Previous', 'respage', $page - 1, $state, disabled: $page === 0);
            $buttons[] = $this->navButton('Next', 'respage', $page + 1, $state, disabled: $page === $totalPages - 1);
        }

        $buttons[] = ['type' => 2, 'style' => 2, 'label' => 'Change category', 'custom_id' => 'gb:changecat::'.$stateRaw];
        $buttons[] = ['type' => 2, 'style' => 2, 'label' => 'Change page', 'custom_id' => 'gb:changepage::'.$stateRaw];
        $buttons[] = ['type' => 2, 'style' => 2, 'label' => 'Start over', 'custom_id' => 'gb:reset::'];

        $routeParams = ['list' => $guide->idlist];

        if ($pageId !== null) {
            $routeParams['page'] = $pageId;
        }

        return [
            'embeds' => [[
                'title' => Str::limit($guide->list_name, 256, ''),
                'url' => rtrim(config('app.url'), '/').route('lists.show', $routeParams, absolute: false),
                'description' => implode("\n", $lines),
            ]],
            'components' => [$this->actionRow($buttons)],
        ];
    }

    /** Categories in scope for $pageId, mirroring ListController::categoriesAndGroupedCombos(). */
    private function scopedCategories(ListModel $guide, ?int $pageId): Collection
    {
        $query = ListCategory::where('list_idlist', $guide->idlist);

        if ($pageId !== null) {
            $query->where(function ($q) use ($pageId) {
                $q->where('idPage', $pageId)->orWhereNull('idPage');
            });
        }

        return $query->orderBy('order')->orderBy('title')->get();
    }

    /** The guide's combos, scoped to $pageId exactly like ListController::categoriesAndGroupedCombos(). */
    private function pageScopedCombos(ListModel $guide, ?int $pageId, Collection $categories): Collection
    {
        $combos = $guide->combos()->with(['character', 'listingType'])->orderByDesc('damage')->get();

        if ($pageId === null) {
            return $combos;
        }

        $categoryIds = $categories->pluck('idlist_category');

        return $combos->filter(
            fn ($combo) => $combo->pivot->list_category_idlist_category === null
                || $categoryIds->contains($combo->pivot->list_category_idlist_category)
        )->values();
    }

    private function resolveGame(string $gameName): ?Game
    {
        $lowerGameName = Str::lower($gameName);

        return Game::whereRaw('LOWER(name) = ?', [$lowerGameName])->first()
            ?? Game::whereHas('aliases', fn ($q) => $q->whereRaw('LOWER(alias) = ?', [$lowerGameName]))->first()
            ?? Game::where('name', 'like', '%'.$gameName.'%')->first()
            ?? Game::whereHas('aliases', fn ($q) => $q->where('alias', 'like', '%'.$gameName.'%'))->first();
    }

    /**
     * Discord nests slash-command sub-command options one level deep
     * (data.options[0] === the `guide-browse` sub-command, whose own
     * `options` array holds `game`/`name`); unwrap that before reading values.
     */
    private function flattenOptions(array $options): array
    {
        if (isset($options[0]['options'])) {
            $options = $options[0]['options'];
        }

        return collect($options)->pluck('value', 'name')->all();
    }

    private function summaryEmbed(ListModel $guide, string $prompt, ?int $pageId = null): array
    {
        $lines = ["**Guide:** {$guide->list_name}"];

        if ($guide->game) {
            $lines[] = "**Game:** {$guide->game->name}";
        }

        if ($pageId !== null) {
            $page = ListPage::find($pageId);

            if ($page) {
                $lines[] = "**Page:** {$page->Title}";
            }
        }

        $lines[] = '';
        $lines[] = $prompt;

        return ['title' => 'Browse a guide', 'description' => implode("\n", $lines)];
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

    private function withoutKeys(array $state, array $keys): array
    {
        return collect($state)->except($keys)->all();
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

    private function navButton(string $label, string $action, int $target, array $state, bool $disabled): array
    {
        return [
            'type' => 2,
            'style' => 2,
            'label' => $label,
            'custom_id' => "gb:{$action}:{$target}:".$this->encodeState($state),
            'disabled' => $disabled,
        ];
    }

    private function ephemeral(string $content): array
    {
        return ['content' => $content, 'flags' => 64];
    }
}
