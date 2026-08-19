@props(['game'])

<div class="col my-3">
    <div class="card text-center w-100 p-3 h-100 combosuki-main-reversed">
        <div class="card text-center w-100 p-3 h-100" style="background-color: var(--combosuki-bg-color);">
            <a href="{{ route('games.show', $game) }}" class="d-flex align-items-center justify-content-center mx-auto"
               style="width:200px; height:200px;">
                <img class="rounded"
                     style="max-height:100%; max-width:100%; height:auto; width:auto; object-fit:contain;"
                     src="{{ $game->image }}" alt="{{ $game->name }}">
            </a>
        </div>
        <div class="card-body">
            <a class="card-title text-white" href="{{ route('games.show', $game) }}">{{ $game->name }}</a>
        </div>
    </div>
</div>
