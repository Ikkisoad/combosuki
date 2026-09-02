<x-layouts.app :title="$game->name.' - Combo好き'" :description="$game->description">
    <x-jumbotron :height="100" />
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @can('update', $game)
            <a href="{{ route('admin.game.edit', $game) }}" class="btn btn-secondary btn-sm mb-2" style="float: right;">Edit Game</a>
        @endcan

        <p class="text-white-50 small mb-2">{{ number_format($game->views) }} {{ \Illuminate\Support\Str::plural('view', $game->views) }}</p>

        <form method="get" action="{{ route('games.combos.index', $game) }}" class="card bg-dark p-2 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <select name="characterid" class="form-select">
                        <option value="-">Character</option>
                        @foreach ($characters as $character)
                            <option value="{{ $character->idcharacter }}">{{ $character->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <select name="listingtype" class="form-select">
                        <option value="-">Show All</option>
                        @foreach ($listingTypes as $entry)
                            <option value="{{ $entry->entryid }}">{{ $entry->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto flex-grow-1">
                    <textarea name="combo" class="form-control" rows="1" placeholder="Starter"></textarea>
                </div>
                @foreach ($primaryResources as $resource)
                    @php $field = str_replace(' ', '_', $resource->text_name); @endphp
                    <div class="col-auto">
                        @if ($resource->type === 1)
                            <select name="{{ $field }}" class="form-select">
                                <option value="-">{{ $resource->text_name }}</option>
                                @foreach ($resource->values->sortBy([['order', 'asc'], ['value', 'asc']]) as $value)
                                    <option value="{{ $value->idResources_values }}">{{ $value->value }}</option>
                                @endforeach
                            </select>
                        @elseif ($resource->type === 2)
                            @php $bound = $resource->values->first()?->value; @endphp
                            <input type="number" name="{{ $field }}" class="form-control" min="-{{ $bound }}" max="{{ $bound }}" step="any" placeholder="{{ $resource->text_name }}">
                        @else
                            @for ($i = 0; $i < 2; $i++)
                                <select name="{{ $field }}[]" class="form-select d-inline-block w-auto">
                                    <option value="-">{{ $resource->text_name }}</option>
                                    @foreach ($resource->values->sortBy([['order', 'asc'], ['value', 'asc']]) as $value)
                                        <option value="{{ $value->idResources_values }}">{{ $value->value }}</option>
                                    @endforeach
                                </select>
                            @endfor
                        @endif
                    </div>
                @endforeach
                <div class="col-auto">
                    <button type="submit" class="btn btn-info">Quick Search</button>
                </div>
                <div class="col-auto">
                    <a href="{{ route('games.combos.index', $game) }}" class="btn btn-secondary">Advanced Search</a>
                </div>
            </div>
        </form>

        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar show collapse sidebar-backdrop">
                <h3>Entries per Character</h3>
                <table class="sidebar-character-table w-100">
                    <tbody>
                        @foreach ($characters as $character)
                            <tr>
                                <td>{{ $character->name }}</td>
                                <td class="text-end">{{ $character->combos_count }}</td>
                                <td class="text-end">
                                    <a href="{{ route('characters.show', [$game, $character]) }}" class="sidebar-character-link" aria-label="View {{ $character->name }}'s page">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M5 3l6 5-6 5" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                @if ($game->description)
                    <p>{{ $game->description }}</p>
                @endif

                @if ($game->links->isNotEmpty())
                    <div class="sidebar-backdrop mb-3 d-inline-block">
                        <h3>Related Links</h3>
                        <div class="d-flex flex-wrap column-gap-4 row-gap-2">
                            @foreach ($game->links as $link)
                                <a href="{{ $link->Link }}" target="_blank" class="sidebar-character-link align-items-center gap-1" aria-label="Open {{ $link->Title }}">
                                    {{ $link->Title }}
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M5 3l6 5-6 5" />
                                    </svg>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <ul class="nav nav-tabs mt-3" id="game-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="combos-tab" data-bs-toggle="tab" data-bs-target="#combos-pane" type="button" role="tab" aria-controls="combos-pane" aria-selected="true">Latest Combos</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="most-viewed-tab" data-bs-toggle="tab" data-bs-target="#most-viewed-pane" type="button" role="tab" aria-controls="most-viewed-pane" aria-selected="false">Most Viewed</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="guides-tab" data-bs-toggle="tab" data-bs-target="#guides-pane" type="button" role="tab" aria-controls="guides-pane" aria-selected="false">Guides</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tier-lists-tab" data-bs-toggle="tab" data-bs-target="#tier-lists-pane" type="button" role="tab" aria-controls="tier-lists-pane" aria-selected="false">Tier Lists</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="damage-stats-tab" data-bs-toggle="tab" data-bs-target="#damage-stats-pane" type="button" role="tab" aria-controls="damage-stats-pane" aria-selected="false">Damage Stats</button>
                    </li>
                    @if ($game->matches_enabled || $game->matches_url)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="matches-tab" data-bs-toggle="tab" data-bs-target="#matches-pane" type="button" role="tab" aria-controls="matches-pane" aria-selected="false">Latest Matches</button>
                        </li>
                    @endif
                </ul>

                <div class="tab-content combosuki-main-reversed text-white p-3 border border-top-0" id="game-tabs-content">
                    <div class="tab-pane fade show active" id="combos-pane" role="tabpanel" aria-labelledby="combos-tab">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h4 class="mb-0">Latest Combos</h4>
                            <a href="{{ route('games.combos.create', $game) }}" class="btn btn-combosuki text-white btn-sm">Submit a combo</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle caption-top text-white">
                                <caption>Click the character name to see comments if the entry has them.</caption>
                                <tr>
                                    <th>Character</th>
                                    <th>Inputs</th>
                                    <th>Damage</th>
                                    <th>Type</th>
                                    <th>Submitted</th>
                                </tr>
                                @foreach ($latestCombos as $combo)
                                    <tr>
                                        <td>
                                            @if ($combo->comments || $combo->video)
                                                <button class="btn btn-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $combo->idcombo }}">{{ $combo->character->name }}</button>
                                            @else
                                                {{ $combo->character->name }}
                                            @endif
                                        </td>
                                        <td style="min-width:400px">
                                            <x-combo-link :combo="$combo" />
                                            @if ($combo->comments || $combo->video)
                                                <div class="collapse" id="collapse{{ $combo->idcombo }}">
                                                    {{ $combo->comments }}
                                                    <x-video-embed :video="$combo->video" />
                                                </div>
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $combo->damage, 0, '', '.') }}</td>
                                        <td>{{ $combo->listingType?->title }}</td>
                                        <td>{{ $combo->submited?->format('d-m-y') }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                        <a href="{{ route('games.combos.index', $game) }}?search=1" class="link-light">View all combos &rarr;</a>
                    </div>

                    <div class="tab-pane fade" id="most-viewed-pane" role="tabpanel" aria-labelledby="most-viewed-tab">
                        <div id="most-viewed-results" data-endpoint="{{ route('games.tabs.most-viewed', $game) }}"></div>
                    </div>

                    <div class="tab-pane fade" id="guides-pane" role="tabpanel" aria-labelledby="guides-tab">
                        <div id="guides-results" data-endpoint="{{ route('games.tabs.guides', $game) }}"></div>
                    </div>

                    <div class="tab-pane fade" id="tier-lists-pane" role="tabpanel" aria-labelledby="tier-lists-tab">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h4 class="mb-0">Perceived Strength</h4>
                            <a href="{{ route('tier-lists.create') }}" class="btn btn-primary btn-sm">Make a Tier List</a>
                        </div>

                        <form class="row g-2 align-items-end mb-3" id="tier-date-range-form">
                            <div class="col-auto">
                                <label for="tier_patch" class="form-label small mb-0">Patch</label>
                                <select id="tier_patch" name="tier_patch" class="form-select form-select-sm">
                                    <option value="all" @selected($selectedTierPatch === 'all')>All time</option>
                                    @foreach ($patches as $patch)
                                        <option value="{{ $patch->idgame_patch }}" @selected($selectedTierPatch == $patch->idgame_patch)>
                                            {{ $patch->label }}@if ($patch->isCurrent()) (current) @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-info btn-sm">Filter</button>
                            </div>
                        </form>

                        <div id="tier-lists-results" data-endpoint="{{ route('games.tabs.tier-lists', $game) }}"></div>

                        <a href="{{ route('tier-lists.index', ['game_idgame' => $game->idgame]) }}" class="link-light">View all tier lists &rarr;</a>
                    </div>

                    <div class="tab-pane fade" id="damage-stats-pane" role="tabpanel" aria-labelledby="damage-stats-tab">
                        <div id="damage-stats-results" data-endpoint="{{ route('games.tabs.damage-stats', $game) }}"></div>
                    </div>

                    @if ($game->matches_enabled || $game->matches_url)
                        <div class="tab-pane fade" id="matches-pane" role="tabpanel" aria-labelledby="matches-tab">
                            <div id="matches-results" data-endpoint="{{ route('games.tabs.matches', $game) }}"></div>
                        </div>
                    @endif
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var guidesTabButton = document.getElementById('guides-tab');
            var guidesResults = document.getElementById('guides-results');
            var mostViewedTabButton = document.getElementById('most-viewed-tab');
            var mostViewedResults = document.getElementById('most-viewed-results');
            var damageStatsTabButton = document.getElementById('damage-stats-tab');
            var damageStatsResults = document.getElementById('damage-stats-results');
            var matchesTabButton = document.getElementById('matches-tab');
            var matchesResults = document.getElementById('matches-results');
            var tierListsTabButton = document.getElementById('tier-lists-tab');
            var tierListsPane = document.getElementById('tier-lists-pane');
            var tierResults = document.getElementById('tier-lists-results');
            var tierForm = document.getElementById('tier-date-range-form');
            var tierPatchInput = document.getElementById('tier_patch');

            function loadGuides() {
                if (guidesResults.dataset.loaded === '1') {
                    return;
                }
                guidesResults.innerHTML = '<p class="text-white-50">Loading&hellip;</p>';
                fetch(guidesResults.dataset.endpoint)
                    .then(function (response) { return response.text(); })
                    .then(function (html) {
                        guidesResults.innerHTML = html;
                        guidesResults.dataset.loaded = '1';
                    })
                    .catch(function () {
                        guidesResults.innerHTML = '<p class="text-danger">Failed to load guides.</p>';
                    });
            }

            function loadMostViewed() {
                if (mostViewedResults.dataset.loaded === '1') {
                    return;
                }
                mostViewedResults.innerHTML = '<p class="text-white-50">Loading&hellip;</p>';
                fetch(mostViewedResults.dataset.endpoint)
                    .then(function (response) { return response.text(); })
                    .then(function (html) {
                        mostViewedResults.innerHTML = html;
                        mostViewedResults.dataset.loaded = '1';
                    })
                    .catch(function () {
                        mostViewedResults.innerHTML = '<p class="text-danger">Failed to load most viewed combos.</p>';
                    });
            }

            function loadDamageStats() {
                if (damageStatsResults.dataset.loaded === '1') {
                    return;
                }
                damageStatsResults.innerHTML = '<p class="text-white-50">Loading&hellip;</p>';
                fetch(damageStatsResults.dataset.endpoint)
                    .then(function (response) { return response.text(); })
                    .then(function (html) {
                        damageStatsResults.innerHTML = html;
                        damageStatsResults.dataset.loaded = '1';
                    })
                    .catch(function () {
                        damageStatsResults.innerHTML = '<p class="text-danger">Failed to load damage stats.</p>';
                    });
            }

            function loadTierLists() {
                var params = new URLSearchParams();
                if (tierPatchInput.value) {
                    params.set('tier_patch', tierPatchInput.value);
                }
                tierResults.innerHTML = '<p class="text-white-50">Loading&hellip;</p>';
                fetch(tierResults.dataset.endpoint + '?' + params.toString())
                    .then(function (response) { return response.text(); })
                    .then(function (html) {
                        tierResults.innerHTML = html;
                        tierResults.dataset.loaded = '1';
                    })
                    .catch(function () {
                        tierResults.innerHTML = '<p class="text-danger">Failed to load tier lists.</p>';
                    });
            }

            function loadMatches() {
                if (matchesResults.dataset.loaded === '1') {
                    return;
                }
                matchesResults.innerHTML = '<p class="text-white-50">Loading&hellip;</p>';
                fetch(matchesResults.dataset.endpoint)
                    .then(function (response) { return response.text(); })
                    .then(function (html) {
                        matchesResults.innerHTML = html;
                        matchesResults.dataset.loaded = '1';
                    })
                    .catch(function () {
                        matchesResults.innerHTML = '<p class="text-danger">Failed to load matches.</p>';
                    });
            }

            function activateTab(tabButton, pane) {
                document.querySelectorAll('#game-tabs .nav-link').forEach(function (el) {
                    el.classList.remove('active');
                    el.setAttribute('aria-selected', 'false');
                });
                document.querySelectorAll('#game-tabs-content .tab-pane').forEach(function (el) {
                    el.classList.remove('show', 'active');
                });
                tabButton.classList.add('active');
                tabButton.setAttribute('aria-selected', 'true');
                pane.classList.add('show', 'active');
            }

            guidesTabButton.addEventListener('shown.bs.tab', loadGuides);
            mostViewedTabButton.addEventListener('shown.bs.tab', loadMostViewed);
            damageStatsTabButton.addEventListener('shown.bs.tab', loadDamageStats);

            if (matchesTabButton) {
                matchesTabButton.addEventListener('shown.bs.tab', loadMatches);
            }

            tierListsTabButton.addEventListener('shown.bs.tab', function () {
                if (tierResults.dataset.loaded !== '1') {
                    loadTierLists();
                }
            });

            tierForm.addEventListener('submit', function (event) {
                event.preventDefault();
                loadTierLists();
            });

            var params = new URLSearchParams(window.location.search);
            if (params.has('tier_patch')) {
                activateTab(tierListsTabButton, tierListsPane);
                loadTierLists();
            }
        });
    </script>
</x-layouts.app>
