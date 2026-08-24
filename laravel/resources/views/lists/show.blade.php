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
                            <a class="nav-link @if ($pageId === 0) active @endif" data-page-id="0" href="{{ route('lists.show', $list) }}">First Page</a>
                        </li>
                        @foreach ($list->pages->sortBy('order') as $page)
                            <li class="nav-item">
                                <a class="nav-link @if ($pageId === $page->idListPage) active @endif" data-page-id="{{ $page->idListPage }}" href="{{ route('lists.show', $list) }}?page={{ $page->idListPage }}">{{ $page->Title }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <div id="list-page-description">
                        @include('lists._page-description', ['currentPage' => $currentPage])
                    </div>
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
                    &middot; Created {{ $list->created_at?->format('M j, Y') }}
                    &middot; {{ number_format($list->views) }} {{ \Illuminate\Support\Str::plural('view', $list->views) }}
                </p>

                @if ($list->game)
                    <div class="row mb-2">
                        <div class="col">
                            <img src="{{ $list->game->logo_url }}" height="19" title="{{ $list->game->name }}">
                            {{ $list->game->name }} list
                        </div>
                    </div>
                @endif

                @can('update', $list)
                    <div class="mb-3">
                        <a href="{{ route('lists.manage.index', $list) }}" class="btn btn-secondary">Manage this list &rarr;</a>
                    </div>
                @endcan

                <div id="list-page-body">
                    @include('lists._page-body', ['categories' => $categories, 'grouped' => $grouped])
                </div>

            </main>
        </div>
    </div>

    @if ($list->pages->isNotEmpty())
        @vite(['resources/js/lists-show.js'])
    @endif
</x-layouts.app>
