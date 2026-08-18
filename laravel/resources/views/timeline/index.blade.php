<x-layouts.app :title="'Timeline - Combo好き'" description="The latest combos submitted across every game.">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container-fluid my-3">
        <div class="row">
            <main class="col-12 px-md-4">
                <h2>Timeline</h2>
                <p>The latest combos submitted across every game.</p>

                <div class="table-responsive">
                    <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                        <caption>Click the character name to see comments if the entry has them.</caption>
                        <tr>
                            <th>Game</th>
                            <th>Character</th>
                            <th>Inputs</th>
                            <th>Damage</th>
                            <th>Type</th>
                            <th>Submitted</th>
                        </tr>
                        @foreach ($combos as $combo)
                            <tr>
                                <td>
                                    <a href="{{ route('games.show', $combo->character->game) }}" class="text-white">{{ $combo->character->game->name }}</a>
                                </td>
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

                {{ $combos->links('pagination::bootstrap-5') }}
            </main>
        </div>
    </div>
</x-layouts.app>
