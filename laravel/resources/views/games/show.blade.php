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

        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar show collapse">
                <h3>Entries per Character</h3>
                <ul class="list-unstyled">
                    @foreach ($characters as $character)
                        <li>{{ $character->name }}: {{ $character->combos_count }}</li>
                    @endforeach
                </ul>
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

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <h2>Latest submissions</h2>
                    <a href="{{ route('games.combos.create', $game) }}" class="btn btn-combosuki text-white">Submit a combo</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
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
                                    @if ($combo->comments)
                                        <button class="btn btn-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $combo->idcombo }}">{{ $combo->character->name }}</button>
                                    @else
                                        {{ $combo->character->name }}
                                    @endif
                                </td>
                                <td style="min-width:400px">
                                    <a href="{{ route('combos.show', $combo) }}">{{ $combo->combo }}</a>
                                    @if ($combo->comments)
                                        <div class="collapse" id="collapse{{ $combo->idcombo }}">{{ $combo->comments }}</div>
                                    @endif
                                </td>
                                <td>{{ number_format((float) $combo->damage, 0, '', '.') }}</td>
                                <td>{{ $combo->listingType?->title }}</td>
                                <td>{{ $combo->submited?->format('d-m-y') }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </main>
        </div>
    </div>
</x-layouts.app>
