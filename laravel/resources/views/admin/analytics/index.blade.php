@php
    $typeLabels = [
        'games' => 'Games',
        'combos' => 'Combos',
        'characters' => 'Characters',
        'guides' => 'Guides',
        'tierLists' => 'Tier Lists',
        'combleDays' => 'Comble Days',
    ];

    $itemWords = [
        'combleDays' => 'day',
    ];

    $maxTypeViews = max(1, ...array_column($totals, 'views'));

    $topGamesRows = $topGames->map(fn ($g) => [
        'label' => $g->name,
        'sublabel' => null,
        'views' => $g->views,
        'url' => route('games.show', $g),
    ])->all();

    $topCombosRows = $topCombos->map(fn ($c) => [
        'label' => \Illuminate\Support\Str::limit($c->combo, 40),
        'sublabel' => $c->character->name.' — '.$c->character->game->name,
        'views' => $c->views,
        'url' => route('combos.show', $c),
    ])->all();

    $topCharactersRows = $topCharacters->map(fn ($c) => [
        'label' => $c->name,
        'sublabel' => $c->game?->name,
        'views' => $c->views,
        'url' => route('characters.show', [$c->game, $c]),
    ])->all();

    $topGuidesRows = $topGuides->map(fn ($l) => [
        'label' => $l->list_name,
        'sublabel' => $l->game?->name,
        'views' => $l->views,
        'url' => route('lists.show', $l),
    ])->all();

    $topTierListsRows = $topTierLists->map(fn ($t) => [
        'label' => $t->title,
        'sublabel' => $t->game?->name,
        'views' => $t->views,
        'url' => route('tier-lists.show', $t),
    ])->all();

    $topCombleDaysRows = $topCombleDays->map(fn ($row) => [
        'label' => $row['day']->format('M j, Y'),
        'sublabel' => $row['game']->name.' — '.$row['character']->name,
        'views' => $row['views'],
        'url' => $row['day']->isToday()
            ? route('comble.show')
            : route('comble.show.date', ['date' => $row['day']->toDateString()]),
    ])->all();

    $topBotPagesRows = $topBotPages->map(fn ($p) => [
        'label' => $p->path,
        'sublabel' => null,
        'views' => $p->hits,
        'url' => $p->path,
    ])->all();

    $topDiscordCommandsRows = $topDiscordCommands->map(fn ($c) => [
        'label' => $c->command,
        'sublabel' => null,
        'views' => $c->uses,
        'url' => null,
    ])->all();
@endphp
<x-layouts.app title="Admin Analytics">
    <x-nav-bar />

    <div class="container-fluid my-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h1 class="text-white">View Analytics</h1>
            <a href="{{ route('admin.analytics') }}" class="btn btn-outline-light btn-sm">Refresh</a>
        </div>
        <p class="text-white-50">
            <a href="{{ route('admin.dashboard') }}" class="link-light">&larr; Back to Admin Dashboard</a>
        </p>

        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-5 g-3 mb-3">
            @foreach ($totals as $key => $data)
                @php $avg = $data['count'] > 0 ? $data['views'] / $data['count'] : 0; @endphp
                <div class="col">
                    <div class="card combosuki-main-reversed text-white p-3 h-100">
                        <div class="text-white-50 small text-uppercase">{{ $typeLabels[$key] }}</div>
                        <div class="fs-3 fw-bold">{{ number_format($data['views']) }}</div>
                        <div class="text-white-50 small">
                            {{ number_format($data['count']) }} {{ \Illuminate\Support\Str::plural($itemWords[$key] ?? 'item', $data['count']) }}
                            &middot; {{ number_format($avg, 1) }} avg
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="col">
                <div class="card combosuki-main-reversed text-white p-3 h-100">
                    <div class="text-white-50 small text-uppercase">Discord Commands</div>
                    <div class="fs-3 fw-bold">{{ number_format($totalDiscordCommandUses) }}</div>
                    <div class="text-white-50 small">
                        {{ \Illuminate\Support\Str::plural('invocation', $totalDiscordCommandUses) }} recorded
                        @if ($discordGuildCount !== null)
                            &middot; active in {{ number_format($discordGuildCount) }} {{ \Illuminate\Support\Str::plural('server', $discordGuildCount) }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="col">
                <div class="card combosuki-main-reversed text-white p-3 h-100">
                    <div class="text-white-50 small text-uppercase">Bot Traffic</div>
                    <div class="fs-3 fw-bold">{{ number_format($totalBotHits) }}</div>
                    <div class="text-white-50 small">
                        honeypot {{ \Illuminate\Support\Str::plural('hit', $totalBotHits) }} recorded
                    </div>
                </div>
            </div>
        </div>

        <div class="card combosuki-main-reversed text-white p-3 mb-3">
            <h4>Views by Content Type</h4>
            @foreach ($totals as $key => $data)
                <div class="d-flex align-items-center gap-2 mb-1">
                    <div style="width: 100px;" class="small">{{ $typeLabels[$key] }}</div>
                    <div class="flex-grow-1 bg-dark rounded">
                        <div
                            class="bg-success rounded d-flex align-items-center justify-content-end px-2 text-dark small fw-bold"
                            style="height: 20px; width: {{ max(6, round($data['views'] / $maxTypeViews * 100)) }}%;"
                        >
                            {{ number_format($data['views']) }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-lg-6">
                <x-admin.top-list title="Top 10 Games" :rows="$topGamesRows" />
                <x-admin.top-list title="Top 10 Characters" :rows="$topCharactersRows" />
                <x-admin.top-list title="Top 10 Tier Lists" :rows="$topTierListsRows" />
            </div>
            <div class="col-lg-6">
                <x-admin.top-list title="Top 10 Combos" :rows="$topCombosRows" />
                <x-admin.top-list title="Top 10 Guides" :rows="$topGuidesRows" />
                <x-admin.top-list title="Top 10 Comble Days" :rows="$topCombleDaysRows" />
            </div>
        </div>

        <x-admin.top-list title="Top 10 Pages by Bot Hits" :rows="$topBotPagesRows" />
        <x-admin.top-list title="Top Discord Commands" :rows="$topDiscordCommandsRows" />
    </div>
</x-layouts.app>
