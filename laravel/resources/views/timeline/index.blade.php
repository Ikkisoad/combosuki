<x-layouts.app :title="'Timeline - Combo好き'" description="The latest combos submitted across every game.">
    <x-jumbotron :height="150" />
    <x-nav-bar />

    <div class="container my-3">
        <main>
            <h2>Timeline</h2>
            <p>The latest combos submitted across every game.</p>

            <div id="timeline-list" data-next-page-url="{{ $combos->nextPageUrl() }}">
                @foreach ($combos as $combo)
                    @include('timeline._combo', ['combo' => $combo])
                @endforeach
            </div>

            <div class="text-center mb-3 d-flex justify-content-center align-items-center gap-2">
                <button type="button" id="timeline-load-more" class="btn btn-secondary" style="{{ $combos->nextPageUrl() ? '' : 'display: none;' }}">Load more</button>
                <button type="button" id="timeline-back-to-top" class="btn btn-outline-light" title="Back to top" aria-label="Back to top">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5"/>
                    </svg>
                </button>
            </div>
        </main>
    </div>

    @vite(['resources/js/timeline.js'])
</x-layouts.app>
