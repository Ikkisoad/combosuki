@props(['game'])

<div class="col my-3">
    <div class="card text-center w-100 p-3 h-100 combosuki-main-reversed">
        <div class="card text-center w-100 p-3 h-100" style="background-color: var(--combosuki-bg-color);">
            <a href="{{ route('games.show', $game) }}" class="d-flex align-items-center justify-content-center mx-auto"
               style="width:200px; height:200px;">
                <img class="rounded" loading="lazy"
                     style="max-height:100%; max-width:100%; height:auto; width:auto; object-fit:contain;"
                     src="{{ $game->logo_url }}" alt="{{ $game->name }}">
            </a>
        </div>
        <div class="card-body">
            <a class="card-title text-white" href="{{ route('games.show', $game) }}">{{ $game->name }}</a>
            @if ($game->combos_count !== null)
                <div class="text-muted small">{{ trans_choice('{0} No submissions|{1} 1 submission|[2,*] :count submissions', $game->combos_count, ['count' => $game->combos_count]) }}</div>
            @endif
            @if ($game->show_unverified_highlight ?? false)
                <span class="badge bg-warning text-dark">Has unverified combos</span>
            @endif
        </div>
    </div>
</div>
