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
                    <li class="nav-item"><a class="nav-link" href="https://github.com/Ikkisoad/combosuki" target="_blank">GitHub</a></li>
                @endif
                <li class="nav-item">
                    <a class="nav-link" href="/lists">Guides</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/timeline">Timeline</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        More
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="/games/add">Add Game</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/combo-guidelines">Combo Guidelines</a></li>
                        <li><a class="dropdown-item" href="https://srk.shib.live/w/Shoryuken_Wiki:Community_portal/Discords/Game" target="_blank">FGC Discord Compendium</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/preferences">Preferences</a></li>
                        <li><a class="dropdown-item" href="/randomizer">Randomizers</a></li>
                        <li><a class="dropdown-item" href="/logs">Logs</a></li>
                    </ul>
                </li>
            </ul>
            {{ $actions ?? '' }}
        </div>
    </div>
</nav>
