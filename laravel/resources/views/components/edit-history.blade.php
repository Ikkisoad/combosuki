@props(['histories'])

<section class="card combosuki-main-reversed text-white p-3 mb-4">
    <h4>History</h4>

    @forelse ($histories as $entry)
        <div class="small text-white-50">
            {{ ucfirst($entry->action) }} by {{ $entry->user?->nickname ?? 'Unknown' }} &mdash; {{ $entry->created_at?->diffForHumans() }}
        </div>
    @empty
        <p class="small text-white-50 mb-0">No edits recorded yet.</p>
    @endforelse
</section>
