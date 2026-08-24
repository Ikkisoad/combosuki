@php
    $ogVideo = app(\App\Services\VideoEmbedResolver::class)->openGraph($combo->video);

    $ogDescription = trim(sprintf(
        '%s%s combo for %s in %s',
        \Illuminate\Support\Str::limit($combo->combo, 100),
        $combo->damage ? ' ('.number_format((float) $combo->damage, 0, '', '.').' dmg)' : '',
        $combo->character->name,
        $game->name,
    ));

    $ogImage = $ogVideo['image'] ?? null;

    if (! $ogImage) {
        $ogImage = $game->logo_url;
    }
@endphp
<x-layouts.app
    :title="$combo->character->name.' - Combo好き'"
    :description="$ogDescription"
    :image="$ogImage"
    :player="$ogVideo"
>
    <x-jumbotron :height="200" />
    <x-nav-bar :game="$game" />

    <div class="container-fluid px-5 my-3">
        @if (session('status'))
            <div class="alert alert-success d-flex justify-content-between align-items-center">
                <span>{{ session('status') }}</span>
                @auth
                    <a href="{{ route('games.combos.create', $game) }}" class="btn btn-sm btn-success">Submit another combo</a>
                @endauth
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row">
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div id="combo-view">
                    <table class="table table-hover align-middle combosuki-main-reversed text-white">
                        <tr>
                            <th>
                                Entry ID: {{ $combo->idcombo }} / {{ $combo->character->name }}
                                {{ $combo->listingType?->title }}
                                <span class="text-white-50 small fw-normal">&middot; {{ number_format($combo->views) }} {{ \Illuminate\Support\Str::plural('view', $combo->views) }}</span>
                                @if ($combo->patch)
                                    <button class="btn btn-dark" style="float: right;" disabled>Patch: {{ $combo->patch }}</button>
                                @endif
                                @can('update', $combo)
                                    <button type="button" class="btn btn-primary" style="float: right;" onclick="showComboEdit()">Edit</button>
                                @endcan
                                <button style="float: right;" class="btn btn-secondary" onclick="change_display()">Display Method</button>
                                @auth
                                    <button type="button"
                                            class="btn {{ $isFavorited ? 'btn-warning' : 'btn-outline-warning' }}"
                                            style="float: right;"
                                            data-favorited="{{ $isFavorited ? '1' : '0' }}"
                                            onclick="toggleFavorite(this, {{ $combo->idcombo }})">
                                        {{ $isFavorited ? '★ Favorited' : '☆ Favorite' }}
                                    </button>
                                    @if ($userLists->isNotEmpty())
                                        <div class="dropdown d-inline-block" style="float: right;">
                                            <button class="btn btn-secondary dropdown-toggle" type="button" id="addToListDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                Add to List
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="addToListDropdown">
                                                @foreach ($userLists as $list)
                                                    <li>
                                                        <button type="button" class="dropdown-item" @disabled(in_array($list->idlist, $comboListIds)) onclick="addComboToList(this, {{ $list->idlist }}, {{ $combo->idcombo }})">
                                                            {{ $list->list_name }}@if (in_array($list->idlist, $comboListIds)) &check; @endif
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endauth
                            </th>
                        </tr>
                        <tr>
                            <td id="combo_line"><x-combo-notation :game="$game" :notation="$combo->combo" /></td>
                        </tr>
                    </table>

                    <x-video-embed :video="$combo->video" />

                    <div id="combo_text" style="display: none;">{!! nl2br(e($combo->combo)) !!}</div>

                    @if ($combo->comments)
                        <table class="table table-hover align-middle combosuki-main-reversed text-white">
                            <tr><td>Comment:</td></tr>
                            <tr><td>{!! nl2br(e($combo->comments)) !!}</td></tr>
                        </table>
                    @endif
                </div>

                @can('update', $combo)
                    <div id="combo-edit-form" style="display: none;">
                        <table class="table table-hover align-middle combosuki-main-reversed text-white">
                            <tr>
                                <th>Editing Entry ID: {{ $combo->idcombo }}</th>
                            </tr>
                        </table>

                        <form method="post" action="{{ route('combos.update', $combo) }}">
                            @csrf

                            <div class="input-group mb-3">
                                <label class="input-group-text">Character:</label>
                                <select name="character_idcharacter" class="form-select" required>
                                    @foreach ($characters as $character)
                                        <option value="{{ $character->idcharacter }}" @selected($combo->character_idcharacter == $character->idcharacter)>{{ $character->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="input-group mb-3">
                                <label class="input-group-text">Type:</label>
                                <select name="listingtype" class="form-select" required>
                                    @foreach ($listingTypes as $entry)
                                        <option value="{{ $entry->entryid }}" @selected($combo->type == $entry->entryid)>{{ $entry->title }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-2">
                                @foreach ($buttons as $button)
                                    <button type="button" class="btn btn-sm" style="margin-left:0.25em;margin-bottom:0.5em;background-color: {{ $button->color }};" onclick="moveNumbers('{{ $button->name }}')">{{ $button->name }}</button>
                                @endforeach
                                <button type="button" class="btn btn-sm btn-secondary" onclick="backspace()">&#9003; Backspace</button>
                            </div>

                            <textarea name="combo" class="form-control" id="comboarea" rows="4"
                                      placeholder="{{ $game->notation }}" required>{{ $combo->combo }}</textarea>

                            <div class="row my-3">
                                <div class="col">
                                    <div class="input-group mb-3">
                                        <span class="input-group-text">Damage:</span>
                                        <input class="form-control" type="number" name="damage" min="0" value="{{ $combo->damage }}">
                                    </div>
                                </div>
                                <div class="col">
                                    <div class="input-group mb-3">
                                        <span class="input-group-text">Patch:</span>
                                        <input type="text" name="patch" class="form-control" maxlength="10" value="{{ $combo->patch }}">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col">
                                    <label>Comments:</label>
                                    <textarea name="comments" class="form-control" placeholder="Comments like: Corner only, universal, etc... are recommended to make it easier to search specific situations.">{{ $combo->comments }}</textarea>

                                    <label class="mt-2">Video:</label>
                                    <textarea name="video" class="form-control" rows="1" maxlength="255"
                                              placeholder="Currently supports YouTube, Twitter/X, Streamable, Twitch clips, Imgur, Niconico, Gfycat and MedalTv.">{{ $combo->video }}</textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col my-3 d-flex gap-2 align-items-center">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                    <button type="button" class="btn btn-secondary" onclick="cancelComboEdit()">Cancel</button>
                                    <a href="{{ route('combos.edit', $combo) }}" class="ms-auto">Advanced edit (resources &amp; delete) &rarr;</a>
                                </div>
                            </div>
                        </form>
                    </div>
                @endcan
            </main>

            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar show collapse sidebar-backdrop">
                <table class="table table-hover align-middle combosuki-main-reversed text-white">
                    <tr>
                        <th>Damage</th>
                        <td>{{ number_format((float) $combo->damage, 0, '', '.') }}</td>
                    </tr>
                    @foreach ($primaryResources as $resource)
                        <tr>
                            <th>{{ $resource->resourceValue->gameResource->text_name }}</th>
                            <td>{{ $resource->number_value ?? $resource->resourceValue->value }}</td>
                        </tr>
                    @endforeach
                </table>

                @if ($secondaryResources->isNotEmpty())
                    <table class="table table-hover align-middle combosuki-main-reversed text-white">
                        @foreach ($secondaryResources as $resource)
                            <tr>
                                <th>{{ $resource->resourceValue->gameResource->text_name }}</th>
                                <td>{{ $resource->number_value ?? $resource->resourceValue->value }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                <table class="table table-hover align-middle combosuki-main-reversed text-white">
                    <tr>
                        <th>Author:</th>
                        <td>{{ $combo->user?->nickname ?? 'Anonymous' }}</td>
                    </tr>
                    <tr>
                        <th>Submitted:</th>
                        <td>{{ $combo->submited?->format('d-m-Y') }}</td>
                    </tr>
                </table>

                @if ($similarCombos->isNotEmpty())
                    <table class="table table-hover align-middle combosuki-main-reversed text-white">
                        <tr>
                            <th>Similar combos</th>
                        </tr>
                        @foreach ($similarCombos as $similarCombo)
                            <tr>
                                <td>
                                    <a href="{{ route('combos.show', $similarCombo) }}" class="text-white">
                                        {{ \Illuminate\Support\Str::limit($similarCombo->combo, 40) }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                <a href="{{ route('games.show', $game) }}" class="btn btn-dark">Back to {{ $game->name }}</a>
            </nav>
        </div>
    </div>
</x-layouts.app>
