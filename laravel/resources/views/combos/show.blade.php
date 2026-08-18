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
        $ogImage = $game->image ?: null;
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
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="row">
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <table class="table table-hover align-middle combosuki-main-reversed text-white">
                    <tr>
                        <th>
                            Entry ID: {{ $combo->idcombo }} / {{ $combo->character->name }}
                            {{ $combo->listingType?->title }}
                            @if ($combo->patch)
                                <button class="btn btn-dark" style="float: right;" disabled>Patch: {{ $combo->patch }}</button>
                            @endif
                            @auth
                                <a href="{{ route('combos.edit', $combo) }}" class="btn btn-primary" style="float: right;">Edit</a>
                            @endauth
                            <button style="float: right;" class="btn btn-secondary" onclick="change_display()">Display Method</button>
                        </th>
                    </tr>
                    <tr>
                        <td id="combo_line">{!! nl2br(e($combo->combo)) !!}</td>
                    </tr>
                </table>

                <x-video-embed :video="$combo->video" />

                <div id="combo_text" style="display: none;">
                    <x-combo-notation :game="$game" :notation="$combo->combo" />
                </div>

                @if ($combo->comments)
                    <table class="table table-hover align-middle combosuki-main-reversed text-white">
                        <tr><td>Comment:</td></tr>
                        <tr><td>{!! nl2br(e($combo->comments)) !!}</td></tr>
                    </table>
                @endif
            </main>

            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar show collapse">
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
                        <th>Submitted:</th>
                        <td>{{ $combo->submited?->format('d-m-Y') }}</td>
                    </tr>
                </table>

                <a href="{{ route('games.show', $game) }}" class="btn btn-dark">Back to {{ $game->name }}</a>
            </nav>
        </div>
    </div>
</x-layouts.app>
