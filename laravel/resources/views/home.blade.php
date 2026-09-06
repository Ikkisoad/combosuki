<x-layouts.app>
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container-fluid my-3">
        <div class="row">
            <main class="col-md-9 col-lg-10">
                <div class="card combosuki-main-reversed text-white p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mt-0">Challenge</h2>
                        <a href="{{ route('challenge.show') }}" class="btn btn-sm btn-outline-light">Browse other days</a>
                    </div>
                    <x-daily-challenge :challenge="$challenge" />
                </div>

                <div class="body">
                    <div class="row">
                        @foreach ($games as $game)
                            <x-game-card :game="$game" />
                        @endforeach
                    </div>

                    <div class="text-center my-3">
                        <a href="{{ route('games.index') }}" class="btn btn-combosuki text-white">Check out all games</a>
                    </div>
                </div>
            </main>

            <aside class="col-md-3 col-lg-2">
                <x-donation-bar :hide-when-met="true" />

                <div class="sidebar-backdrop mb-3">
                    <h3>Other features</h3>
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('tier-lists.index') }}" class="sidebar-character-link align-items-center gap-1" aria-label="Open Tier Lists">
                            Tier Lists
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 3l6 5-6 5" />
                            </svg>
                        </a>
                        {{-- Meant to be pointed at directly as an OBS Browser Source, so it opens in its own tab rather than navigating away from the site. --}}
                        <a href="{{ route('input-viewer.index') }}" target="_blank" rel="noopener" class="sidebar-character-link align-items-center gap-1" aria-label="Open Input Viewer">
                            Input Viewer
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 3l6 5-6 5" />
                            </svg>
                        </a>
                        @if (config('services.discord.application_id'))
                            {{-- permissions=2147534848: Send Messages + Embed Links + Attach Files + Use Application Commands, matching what the /csk interaction handlers actually use (see app/Services/DiscordComboSearch.php, DiscordTierListImage.php). --}}
                            <a href="https://discord.com/api/oauth2/authorize?client_id={{ config('services.discord.application_id') }}&permissions=2147534848&scope=bot%20applications.commands" target="_blank" rel="noopener" class="sidebar-character-link align-items-center gap-1" aria-label="Add Discord Bot">
                                Add Discord Bot
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 3l6 5-6 5" />
                                </svg>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="sidebar-backdrop mb-3">
                    <h3>Today's Comble</h3>
                    <p class="mb-2 small text-white-50">
                        {{ number_format($combleStats['totalAttempts']) }} {{ \Illuminate\Support\Str::plural('play', $combleStats['totalAttempts']) }}
                        &middot; {{ $combleStats['winRate'] }}% win rate
                    </p>

                    @if ($combleStats['totalAttempts'] > 0)
                        @php $max = max($combleStats['distribution']); @endphp
                        @foreach ([1, 2, 3, 4, 5, 'lost'] as $bucket)
                            @php $count = $combleStats['distribution'][$bucket]; @endphp
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <div style="width: 14px;" class="text-end small">{{ $bucket === 'lost' ? 'X' : $bucket }}</div>
                                <div class="flex-grow-1 bg-dark rounded">
                                    <div
                                        class="{{ $bucket === 'lost' ? 'bg-danger' : 'bg-success' }} rounded d-flex align-items-center justify-content-end px-2 text-dark small fw-bold"
                                        style="height: 16px; width: {{ $count > 0 ? max(10, round($count / $max * 100)) : 0 }}%;"
                                    >
                                        @if ($count > 0)
                                            {{ $count }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    <a href="{{ route('comble.show') }}" target="_blank" rel="noopener" class="btn btn-combosuki text-white btn-sm w-100 mt-2">Play Comble</a>
                </div>

                <div class="sidebar-backdrop mb-3">
                    <h3>Other FGC websites</h3>
                    <div class="d-flex flex-column gap-2">
                        @foreach ($externalSites as $site)
                            <a href="{{ $site->url }}" target="_blank" class="sidebar-character-link align-items-center gap-1" aria-label="Open {{ $site->title }}">
                                {{ $site->title }}
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 3l6 5-6 5" />
                                </svg>
                            </a>
                        @endforeach
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <x-footer />
</x-layouts.app>
