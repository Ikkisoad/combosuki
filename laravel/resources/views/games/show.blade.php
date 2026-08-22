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

        @if (auth()->check() && auth()->user()->isTrusted())
            <a href="{{ route('admin.game.edit', $game) }}" class="btn btn-secondary btn-sm mb-2" style="float: right;">Edit Game</a>
        @endif

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
                                @foreach ($resource->values->sortBy('order') as $value)
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
                                    @foreach ($resource->values->sortBy('order') as $value)
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
                    <h3>Related Links</h3>
                    <p>
                        @foreach ($game->links as $link)
                            <a href="{{ $link->Link }}" target="_blank">{{ $link->Title }}</a> ▰
                        @endforeach
                    </p>
                @endif

                <ul class="nav nav-tabs mt-3" id="game-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="combos-tab" data-bs-toggle="tab" data-bs-target="#combos-pane" type="button" role="tab" aria-controls="combos-pane" aria-selected="true">Latest Combos</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="guides-tab" data-bs-toggle="tab" data-bs-target="#guides-pane" type="button" role="tab" aria-controls="guides-pane" aria-selected="false">Guides</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tier-lists-tab" data-bs-toggle="tab" data-bs-target="#tier-lists-pane" type="button" role="tab" aria-controls="tier-lists-pane" aria-selected="false">Tier Lists</button>
                    </li>
                </ul>

                <div class="tab-content combosuki-main-reversed text-white p-3 border border-top-0" id="game-tabs-content">
                    <div class="tab-pane fade show active" id="combos-pane" role="tabpanel" aria-labelledby="combos-tab">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h4 class="mb-0">Latest Combos</h4>
                            <a href="{{ route('games.combos.create', $game) }}" class="btn btn-combosuki text-white btn-sm">Submit a combo</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle caption-top">
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
                        <a href="{{ route('games.combos.index', $game) }}" class="link-light">View all combos &rarr;</a>
                    </div>

                    <div class="tab-pane fade" id="guides-pane" role="tabpanel" aria-labelledby="guides-tab">
                        <h4 class="mb-2">Guides</h4>
                        @if ($guides->isEmpty())
                            <p>No guides for this game yet.</p>
                        @else
                            <table class="table table-hover align-middle">
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                </tr>
                                @foreach ($guides as $guide)
                                    <tr>
                                        <td><a href="{{ route('lists.show', $guide) }}" class="text-white">{{ $guide->list_name }}</a></td>
                                        <td>{{ $guide->user?->nickname ?? 'Anonymous' }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                        <a href="{{ route('lists.search', ['game_idgame' => $game->idgame]) }}" class="link-light">View all guides &rarr;</a>
                    </div>

                    <div class="tab-pane fade" id="tier-lists-pane" role="tabpanel" aria-labelledby="tier-lists-tab">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h4 class="mb-0">Perceived Strength</h4>
                            <a href="{{ route('tier-lists.create') }}" class="btn btn-primary btn-sm">Make a Tier List</a>
                        </div>

                        <form method="get" action="{{ route('games.show', $game) }}" class="row g-2 align-items-end mb-3" id="tier-date-range-form">
                            <div class="col-auto">
                                <label for="tier_from" class="form-label small mb-0">From</label>
                                <input type="date" id="tier_from" name="tier_from" value="{{ $tierFrom }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-auto">
                                <label for="tier_to" class="form-label small mb-0">To</label>
                                <input type="date" id="tier_to" name="tier_to" value="{{ $tierTo }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-info btn-sm">Filter</button>
                                @if ($tierFrom || $tierTo)
                                    <a href="{{ route('games.show', $game) }}#tier-lists-pane" class="btn btn-outline-light btn-sm" id="tier-date-range-reset">Reset</a>
                                @endif
                            </div>
                        </form>

                        @if ($tierListAggregate['tierListCount'] === 0)
                            <p>No tier lists for this game yet in the selected range.</p>
                        @else
                            <p class="text-white-50 small">Median of {{ $tierListAggregate['tierListCount'] }} community tier {{ \Illuminate\Support\Str::plural('list', $tierListAggregate['tierListCount']) }}.</p>

                            @foreach (['S', 'A', 'B', 'C', 'D', 'F'] as $tier)
                                <div class="tier-row d-flex align-items-stretch mb-2">
                                    <div class="tier-label tier-{{ strtolower($tier) }} d-flex align-items-center justify-content-center fw-bold">{{ $tier }}</div>
                                    <div class="tier-dropzone flex-grow-1 d-flex flex-wrap gap-2 p-2">
                                        @forelse ($tierListAggregate['tiers'][$tier] as $entry)
                                            <div class="character-card" title="{{ $entry['votes'] }} {{ \Illuminate\Support\Str::plural('vote', $entry['votes']) }}">
                                                @if ($entry['character']->image)
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($entry['character']->image) }}" alt="{{ $entry['character']->name }}">
                                                @endif
                                                <div class="small text-center">{{ $entry['character']->name }}</div>
                                            </div>
                                        @empty
                                            <span class="text-white-50 small">&mdash;</span>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        <a href="{{ route('tier-lists.index', ['game_idgame' => $game->idgame]) }}" class="link-light">View all tier lists &rarr;</a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    @if ($tierFrom || $tierTo)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var tabButton = document.getElementById('tier-lists-tab');
                var tabPane = document.getElementById('tier-lists-pane');
                if (!tabButton || !tabPane) {
                    return;
                }
                document.querySelectorAll('#game-tabs .nav-link').forEach(function (el) {
                    el.classList.remove('active');
                    el.setAttribute('aria-selected', 'false');
                });
                document.querySelectorAll('#game-tabs-content .tab-pane').forEach(function (el) {
                    el.classList.remove('show', 'active');
                });
                tabButton.classList.add('active');
                tabButton.setAttribute('aria-selected', 'true');
                tabPane.classList.add('show', 'active');
            });
        </script>
    @endif
</x-layouts.app>
