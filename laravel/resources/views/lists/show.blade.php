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

                @if ($list->game)
                    <div class="row mb-2">
                        <div class="col">
                            <img src="{{ $list->game->image }}" height="19" title="{{ $list->game->name }}">
                            {{ $list->game->name }} list
                        </div>
                    </div>
                @endif

                <div class="accordion mb-3" id="listSettings">
                    <div class="accordion-item combosuki-main-reversed">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#listSettingsBody">
                                List settings
                            </button>
                        </h2>
                        <div id="listSettingsBody" class="accordion-collapse collapse">
                            <div class="accordion-body">
                                <form method="post" action="{{ route('lists.rename', $list) }}" class="d-flex gap-2 mb-2">
                                    @csrf
                                    <input type="text" name="list_name" maxlength="100" class="form-control" value="{{ $list->list_name }}">
                                    <input type="password" name="password" maxlength="16" class="form-control" placeholder="List Password">
                                    <button class="btn btn-primary text-nowrap">Rename</button>
                                </form>
                                <form method="post" action="{{ route('lists.destroy', $list) }}" class="d-flex gap-2" onsubmit="return confirm('Are you sure you want to delete this list?');">
                                    @csrf
                                    <input type="password" name="password" maxlength="16" class="form-control" placeholder="List Password">
                                    <button class="btn btn-danger text-nowrap">Delete List</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

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
                                    @if ($combo->comments)
                                        <button class="btn btn-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $combo->idcombo }}">{{ $combo->character->name }}</button>
                                    @else
                                        {{ $combo->character->name }}
                                    @endif
                                </td>
                                <td style="min-width:400px">
                                    <a href="{{ route('combos.show', $combo) }}">{{ $combo->combo }}</a>
                                    @if ($combo->comments)
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

                <div class="card combosuki-main-reversed p-3 mt-3">
                    <h3>Add / Remove Entry</h3>
                    <small>Use , to add or remove multiple entries from the list (e.g. 777,26 would add or remove entries 777 and 26).</small>
                    <form method="post" action="{{ route('lists.entries.alter', $list) }}" class="row g-2 mt-2">
                        @csrf
                        <div class="col-auto">
                            <input placeholder="Entry ID" name="comboid" class="form-control" required>
                        </div>
                        <div class="col-auto">
                            <input placeholder="List Password" name="password" type="password" maxlength="16" class="form-control" required>
                        </div>
                        <div class="col-auto">
                            <input placeholder="Category (optional)" name="category" maxlength="45" class="form-control">
                        </div>
                        <div class="col-auto">
                            <button type="submit" name="action" value="Submit" class="btn btn-primary">Add Entry</button>
                            <button type="submit" name="action" value="Delete" class="btn btn-danger">Remove Entry</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</x-layouts.app>
