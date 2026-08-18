@props(['game'])

<div class="col my-3">
    <div class="card text-center w-100 p-3 h-100 combosuki-main-reversed">
        <div class="card text-center w-100 p-3 h-100" style="background-color: var(--combosuki-bg-color);">
            <a href="{{ route('games.show', $game) }}">
                <img class="rounded mx-auto d-block"
                     style="max-height:200px; max-width:200px; height:auto; width:auto; display:block;"
                     src="{{ $game->image }}" alt="{{ $game->name }}">
            </a>
        </div>
        <div class="card-body">
            <a class="card-title text-white" href="{{ route('games.show', $game) }}">{{ $game->name }}</a>
        </div>
    </div>
</div>
