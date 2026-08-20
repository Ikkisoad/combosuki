<x-layouts.app :title="$tierList->title.' - Combo好き'" :description="'A '.$tierList->game->name.' tier list by '.($tierList->user?->nickname ?? 'Anonymous').'.'">
    <x-jumbotron :height="100" />
    <x-nav-bar :game="$tierList->game" />

    <div class="container-fluid my-3">
        <h2>{{ $tierList->title }}</h2>
        <p class="text-white-50">
            {{ $tierList->game->name }}
            &middot; By {{ $tierList->user?->nickname ?? 'Anonymous' }}
            &middot; {{ $tierList->created_at->format('M j, Y') }}
        </p>

        @php $grouped = $tierList->entries->groupBy('tier'); @endphp

        @foreach (['S', 'A', 'B', 'C', 'D', 'F'] as $tier)
            <div class="tier-row d-flex align-items-stretch mb-2">
                <div class="tier-label tier-{{ strtolower($tier) }} d-flex align-items-center justify-content-center fw-bold">{{ $tier }}</div>
                <div class="tier-dropzone flex-grow-1 d-flex flex-wrap gap-2 p-2">
                    @forelse ($grouped->get($tier, collect()) as $entry)
                        <div class="character-card">
                            @if ($entry->character->image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($entry->character->image) }}" alt="{{ $entry->character->name }}">
                            @endif
                            <div class="small text-center">{{ $entry->character->name }}</div>
                        </div>
                    @empty
                        <span class="text-white-50 small">&mdash;</span>
                    @endforelse
                </div>
            </div>
        @endforeach

        <a href="{{ route('tier-lists.create') }}" class="btn btn-primary mt-3">Make your own</a>
        <a href="{{ route('tier-lists.index') }}" class="btn btn-outline-light mt-3">Browse tier lists</a>
    </div>
</x-layouts.app>
