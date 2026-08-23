<x-layouts.app :title="$list->list_name.' - Combo好き'">
    <x-jumbotron :height="150" />
    <x-nav-bar :game="$list->game" />

    <div class="container-fluid my-3">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row">
            @if ($list->pages->isNotEmpty())
                <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar show collapse">
                    <ul class="nav nav-tabs flex-column combosuki-main-reversed">
                        <li class="nav-item">
                            <a class="nav-link @if ($pageId === 0) active @endif" href="{{ route('lists.show', $list) }}">First Page</a>
                        </li>
                        @foreach ($list->pages->sortBy('order') as $page)
                            <li class="nav-item">
                                <a class="nav-link @if ($pageId === $page->idListPage) active @endif" href="{{ route('lists.show', $list) }}?page={{ $page->idListPage }}">{{ $page->Title }}</a>
                            </li>
                        @endforeach
                    </ul>
                    @php $currentPage = $list->pages->firstWhere('idListPage', $pageId); @endphp
                    @if ($currentPage?->Description)
                        <p class="mt-2">{{ $currentPage->Description }}</p>
                    @endif
                </nav>
            @endif

            <main class="{{ $list->pages->isNotEmpty() ? 'col-md-9 ms-sm-auto col-lg-10' : 'col-12' }} px-md-4">
                <h3 class="mt-3">
                    {{ $list->list_name }}
                    @if ($list->type === 2)
                        <img src="{{ asset('img/misc/verified.png') }}" height="13" title="Verified List">
                    @elseif ($list->type === 3)
                        <img src="{{ asset('img/misc/mod.png') }}" height="19" title="Moderated List">
                    @endif
                </h3>
                <p class="text-white-50 mb-2">
                    By {{ $list->user?->nickname ?? 'Anonymous' }}
                    &middot; {{ number_format($list->views) }} {{ \Illuminate\Support\Str::plural('view', $list->views) }}
                </p>

                @if ($list->game)
                    <div class="row mb-2">
                        <div class="col">
                            <img src="{{ $list->game->image }}" height="19" title="{{ $list->game->name }}">
                            {{ $list->game->name }} list
                        </div>
                    </div>
                @endif

                @can('update', $list)
                    <div class="mb-3">
                        <a href="{{ route('lists.manage.index', $list) }}" class="btn btn-secondary">Manage this list &rarr;</a>
                    </div>
                @endcan

                @foreach ($grouped as $categoryId => $combos)
                    <h2 class="mt-3">{{ $categoryId == 0 ? 'No Category' : $categories->get($categoryId)?->title }}</h2>
                    <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                        <tr>
                            <th>Character</th>
                            <th>Inputs</th>
                            <th>Damage</th>
                            <th>Type</th>
                        </tr>
                        @foreach ($combos as $combo)
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
                            </tr>
                        @endforeach
                    </table>
                @endforeach

            </main>
        </div>
    </div>
</x-layouts.app>
