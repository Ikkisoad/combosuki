@props(['title', 'rows'])

@php
    $max = empty($rows) ? 1 : (max(array_column($rows, 'views')) ?: 1);
@endphp

<div class="card combosuki-main-reversed text-white p-3 mb-3">
    <h4>{{ $title }}</h4>

    @if (empty($rows))
        <p class="text-white-50 mb-0">No data yet.</p>
    @else
        @foreach ($rows as $index => $row)
            <div class="d-flex align-items-center gap-2 mb-1">
                <div style="width: 20px;" class="text-end small text-white-50">{{ $index + 1 }}</div>
                <div style="width: 240px; max-width: 45%;" class="small text-truncate">
                    <a href="{{ $row['url'] }}" class="link-light" target="_blank" rel="noopener">{{ $row['label'] }}</a>
                    @if ($row['sublabel'])
                        <span class="text-white-50"> &middot; {{ $row['sublabel'] }}</span>
                    @endif
                </div>
                <div class="flex-grow-1 bg-dark rounded">
                    <div
                        class="bg-info rounded d-flex align-items-center justify-content-end px-2 text-dark small fw-bold"
                        style="height: 18px; width: {{ max(6, round($row['views'] / $max * 100)) }}%;"
                    >
                        {{ number_format($row['views']) }}
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
