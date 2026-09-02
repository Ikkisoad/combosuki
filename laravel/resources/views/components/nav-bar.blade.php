@props(['game' => null])

<nav class="navbar navbar-expand-lg navbar-dark bg-combosuki-main-2">
    @if ($game)
        <a class="navbar-brand" href="{{ url('/') }}">
            <img src="{{ asset('img/selo.png') }}" style="margin-left:20px" width="30" height="30">
        </a>
    @endif
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ url('/') }}">Combo好き</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                @if ($game)
                    <li class="nav-item">
                        <a class="nav-link active" href="/games/{{ $game->idgame }}">{{ $game->name }}</a>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link" href="/about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="/games">Games</a></li>
                @endif
                <li class="nav-item">
                    <a class="nav-link" href="/lists">Guides</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/timeline">Timeline</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('randomizer.index') }}">Randomizers</a>
                </li>
                <li class="nav-item">
                    {{-- Comble lives on its own comble.* subdomain (routes/comble.php) — opened in a new tab rather than navigated to in place, so there's no "how do I get back" to solve on that page at all. --}}
                    <a class="nav-link" href="{{ route('comble.show') }}" target="_blank" rel="noopener">Comble</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        More
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                        @if (auth()->check() && auth()->user()->isTrusted())
                            <li><a class="dropdown-item" href="{{ route('games.create') }}">Add Game</a></li>
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        <li><a class="dropdown-item" href="{{ route('tier-lists.index') }}">Tier Lists</a></li>
                        <li>
                            {{-- Meant to be pointed at directly as an OBS Browser Source, so it opens in its own tab rather than navigating away from the site. --}}
                            <a class="dropdown-item" href="{{ route('input-viewer.index') }}" target="_blank" rel="noopener">Input Viewer</a>
                        </li>
                        <li><a class="dropdown-item" href="https://srk.shib.live/w/Shoryuken_Wiki:Community_portal/Discords/Game" target="_blank">FGC Discord Compendium</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('preferences.edit') }}">Preferences</a></li>
                        <li><a class="dropdown-item" href="{{ route('logs.index') }}">Logs</a></li>
                    </ul>
                </li>
            </ul>
            <ul class="navbar-nav mb-2 mb-lg-0">
                @auth
                    @if (auth()->user()->is_admin)
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.dashboard') }}">Admin</a>
                        </li>
                    @elseif (auth()->user()->isModerator())
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('admin.users.index') }}">Manage Users</a>
                        </li>
                    @endif
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            {{ auth()->user()->nickname }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                            <li><a class="dropdown-item" href="{{ route('users.show', auth()->user()) }}">My Profile</a></li>
                            <li><a class="dropdown-item" href="{{ route('connections.edit') }}">Connected Accounts</a></li>
                            @if (auth()->user()->isTrusted())
                                <li><a class="dropdown-item" href="{{ route('users.create') }}">Create User</a></li>
                            @endif
                            <li>
                                <form method="post" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Log Out</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('login') }}">Login</a>
                    </li>
                @endauth
            </ul>
            {{ $actions ?? '' }}
        </div>
    </div>
</nav>
