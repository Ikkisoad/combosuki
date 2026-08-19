<x-layouts.app :title="'Logs - Combo好き'">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <h2>Log</h2>

        <div class="combosuki-main-reversed p-3">
            @forelse ($logs as $log)
                <div>{{ $log->date->format('Y-m-d') }}: {{ $log->description }}</div>
            @empty
                <div>No log entries yet.</div>
            @endforelse
        </div>
    </div>
</x-layouts.app>
