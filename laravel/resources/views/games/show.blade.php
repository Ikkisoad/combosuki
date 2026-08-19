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
                            <th>Author</th>
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
                                <td>{{ $combo->user?->nickname ?? 'Anonymous' }}</td>
                                <td>{{ $combo->submited?->format('d-m-y') }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </main>
        </div>
    </div>
</x-layouts.app>
