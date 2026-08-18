<x-layouts.app :title="'Guides - Combo好き'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container-fluid my-3">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <h3>Create List</h3>
                <form class="form-control combosuki-main-reversed text-white" method="post" action="{{ route('lists.store') }}">
                    @csrf
                    <div class="mb-2">
                        <input placeholder="List Name" name="list_name" class="form-control" maxlength="100" value="{{ old('list_name') }}">
                    </div>
                    <div class="mb-2">
                        <select name="game_idgame" class="form-select">
                            <option value="0">Game</option>
                            @foreach ($games as $game)
                                <option value="{{ $game->idgame }}" @selected(old('game_idgame') == $game->idgame)>{{ $game->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <button type="submit" class="btn btn-primary btn-block">Create List</button>
                    </div>
                </form>

                <h3>Search List</h3>
                <form class="form-control combosuki-main-reversed text-white" method="get" action="{{ route('lists.search') }}">
                    <div class="mb-2">
                        <input placeholder="List Name" name="list_name" class="form-control" maxlength="45" value="{{ request('list_name') }}">
                    </div>
                    <div class="mb-2">
                        <select name="game_idgame" class="form-select">
                            <option value="0">Game</option>
                            @foreach ($games as $game)
                                <option value="{{ $game->idgame }}" @selected(request('game_idgame') == $game->idgame)>{{ $game->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <button type="submit" class="btn btn-info btn-block">Search</button>
                    </div>
                </form>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (! ($searched ?? false) || $lists->isNotEmpty())
                    <table class="table table-hover align-middle caption-top combosuki-main-reversed text-white">
                        <tr><th>{{ ($searched ?? false) ? 'Search Results' : 'Guides' }}</th></tr>
                        @foreach ($lists as $list)
                            <tr>
                                <td>
                                    <a href="{{ route('lists.show', $list) }}" class="text-white">{{ $list->list_name }}</a>
                                    @if ($list->type === 2)
                                        <img src="{{ asset('img/misc/verified.png') }}" height="13" title="Verified List">
                                    @elseif ($list->type === 3)
                                        <img src="{{ asset('img/misc/mod.png') }}" height="19" title="Moderated List">
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @elseif ($searched ?? false)
                    <p>No lists found.</p>
                @endif
            </main>
        </div>
    </div>
</x-layouts.app>
