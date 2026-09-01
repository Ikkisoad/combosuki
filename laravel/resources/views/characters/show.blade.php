<x-layouts.app :title="$character->name.' - '.$game->name.' - Combo好き'">
    <x-jumbotron :height="100" />
    <x-nav-bar :game="$game" />

    <div class="container-fluid my-3">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar show collapse sidebar-backdrop">
                @if ($character->image)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($character->image) }}" alt="{{ $character->name }}" class="img-fluid rounded mb-2">
                @endif
                <h3>{{ $character->name }}</h3>
                <p class="text-white-50 small">{{ number_format($character->views) }} {{ \Illuminate\Support\Str::plural('view', $character->views) }}</p>
                @if ($averageDamage !== null)
                    <p class="text-white-50 small">{{ number_format((float) $averageDamage, 0, '', '.') }} avg. damage</p>
                @endif
                <a href="{{ route('games.combos.index', $game) }}?characterid={{ $character->idcharacter }}" class="btn btn-secondary btn-sm">View all combos</a>

                @if ($character->links->isNotEmpty())
                    <h3 class="mt-3">Related Links</h3>
                    <div class="d-flex flex-wrap column-gap-4 row-gap-2">
                        @foreach ($character->links as $link)
                            <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="sidebar-character-link align-items-center gap-1" aria-label="Open {{ $link->label }}">
                                {{ $link->label }}
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 3l6 5-6 5" />
                                </svg>
                            </a>
                        @endforeach
                    </div>
                @endif
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <ul class="nav nav-tabs mt-3" id="character-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab" aria-controls="overview-pane" aria-selected="true">Overview</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="flow-chart-tab" data-bs-toggle="tab" data-bs-target="#flow-chart-pane" type="button" role="tab" aria-controls="flow-chart-pane" aria-selected="false">Combo Flow Chart</button>
                    </li>
                </ul>

                <div class="tab-content combosuki-main-reversed text-white p-3 border border-top-0" id="character-tabs-content">
                    <div class="tab-pane fade show active" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab">
                        @if ($queries->isEmpty())
                            <p class="mt-3">This game doesn't have any default queries configured yet.</p>
                        @endif

                        @foreach ($queries as $query)
                            @php $combo = $topCombos->get($query->idquery); @endphp
                            <h2 class="mt-3">{{ $query->label }}</h2>

                            @if ($combo)
                                <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                                    <tr>
                                        <th>Details</th>
                                        <th>Inputs</th>
                                        <th>Damage</th>
                                    </tr>
                                    <tr>
                                        <td>
                                            @if ($combo->comments || $combo->video)
                                                <button class="btn btn-dark btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $query->idquery }}-{{ $combo->idcombo }}">Details</button>
                                            @endif
                                        </td>
                                        <td style="min-width:400px">
                                            <x-combo-link :combo="$combo" />
                                            @if ($combo->comments || $combo->video)
                                                <div class="collapse" id="collapse{{ $query->idquery }}-{{ $combo->idcombo }}">
                                                    {{ $combo->comments }}
                                                    <x-video-embed :video="$combo->video" />
                                                </div>
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $combo->damage, 0, '', '.') }}</td>
                                    </tr>
                                </table>
                            @else
                                <p>No combo found yet &mdash; <a href="{{ route('games.combos.create', $game) }}?query={{ $query->idquery }}&characterid={{ $character->idcharacter }}" class="btn btn-combosuki btn-sm text-white">Submit one</a></p>
                            @endif
                        @endforeach

                        @if ($topDamageCombos->isNotEmpty())
                            <h2 class="mt-3">Top Damage Combos</h2>

                            <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                                <tr>
                                    <th></th>
                                    <th>Inputs</th>
                                    <th>Damage</th>
                                </tr>
                                @foreach ($topDamageCombos as $combo)
                                    <tr>
                                        <td>
                                            @if ($combo->comments || $combo->video)
                                                <button class="btn btn-dark btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-top-{{ $combo->idcombo }}">Details</button>
                                            @endif
                                        </td>
                                        <td style="min-width:400px">
                                            <x-combo-link :combo="$combo" />
                                            @if ($combo->comments || $combo->video)
                                                <div class="collapse" id="collapse-top-{{ $combo->idcombo }}">
                                                    {{ $combo->comments }}
                                                    <x-video-embed :video="$combo->video" />
                                                </div>
                                            @endif
                                        </td>
                                        <td>{{ number_format((float) $combo->damage, 0, '', '.') }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                    </div>

                    <div class="tab-pane fade" id="flow-chart-pane" role="tabpanel" aria-labelledby="flow-chart-tab">
                        <div id="flow-chart-results" data-endpoint="{{ route('characters.tabs.flow-chart', [$game, $character]) }}"></div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var flowChartTabButton = document.getElementById('flow-chart-tab');
            var flowChartResults = document.getElementById('flow-chart-results');

            function loadFlowChart() {
                if (flowChartResults.dataset.loaded === '1') {
                    return;
                }
                flowChartResults.innerHTML = '<p class="text-white-50">Loading&hellip;</p>';
                fetch(flowChartResults.dataset.endpoint)
                    .then(function (response) { return response.text(); })
                    .then(function (html) {
                        flowChartResults.innerHTML = html;
                        flowChartResults.dataset.loaded = '1';
                        document.dispatchEvent(new CustomEvent('combo-flow-chart:loaded'));
                    })
                    .catch(function () {
                        flowChartResults.innerHTML = '<p class="text-danger">Failed to load the combo flow chart.</p>';
                    });
            }

            flowChartTabButton.addEventListener('shown.bs.tab', loadFlowChart);
        });
    </script>

    @vite(['resources/js/combo-flow-chart.js'])
</x-layouts.app>
