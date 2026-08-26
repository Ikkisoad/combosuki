@if ($tierListAggregate['tierListCount'] === 0)
    <p>No tier lists for this game yet in the selected range.</p>
@else
    <p class="text-white-50 small">Median of {{ $tierListAggregate['tierListCount'] }} community tier {{ \Illuminate\Support\Str::plural('list', $tierListAggregate['tierListCount']) }}.</p>

    @foreach (['S', 'A', 'B', 'C', 'D', 'F'] as $tier)
        <div class="tier-row d-flex align-items-stretch mb-2">
            <div class="tier-label tier-{{ strtolower($tier) }} d-flex align-items-center justify-content-center fw-bold">{{ $tier }}</div>
            <div class="tier-dropzone flex-grow-1 d-flex flex-wrap gap-2 p-2">
                @forelse ($tierListAggregate['tiers'][$tier] as $entry)
                    <x-tier-list-character-card
                        :character="$entry['character']"
                        :resource-value="$entry['resourceValue'] ?? null"
                        :title="$entry['votes'].' '.\Illuminate\Support\Str::plural('vote', $entry['votes'])"
                    />
                @empty
                    <span class="text-white-50 small">&mdash;</span>
                @endforelse
            </div>
        </div>
    @endforeach
@endif
