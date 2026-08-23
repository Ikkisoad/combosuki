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
                <a href="{{ route('games.combos.index', $game) }}?characterid={{ $character->idcharacter }}" class="btn btn-secondary btn-sm">View all combos</a>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                @if ($queries->isEmpty())
                    <p class="mt-3">This game doesn't have any default queries configured yet.</p>
                @endif

                @foreach ($queries as $query)
                    @php $combo = $topCombos->get($query->idquery); @endphp
                    <h2 class="mt-3">{{ $query->label }}</h2>

                    @if ($combo)
                        <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                            <tr>
                                <th>Character</th>
                                <th>Inputs</th>
                                <th>Damage</th>
                            </tr>
                            <tr>
                                <td>
                                    @if ($combo->comments || $combo->video)
                                        <button class="btn btn-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $query->idquery }}-{{ $combo->idcombo }}">{{ $combo->character->name }}</button>
                                    @else
                                        {{ $combo->character->name }}
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
            </main>
        </div>
    </div>
</x-layouts.app>
