/**
 * Renders the challenge calendar's year view (12 month grids) client-side.
 * The container itself is present in the initial page render (see
 * challenge/show.blade.php) — only its data is lazy-loaded, deferred until
 * the Calendar tab is first shown, then re-fetched per year as the visitor
 * navigates.
 *
 * The game filter is applied client-side against the per-day game ids the
 * endpoint returns alongside each day's status: days whose challenge was a
 * different game are dimmed and made non-clickable. The selection persists
 * across year changes (names of games seen in earlier years are remembered
 * so the option stays selectable even in a year that game never appeared).
 */
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('challenge-calendar');
    const calendarTabButton = document.getElementById('calendar-tab');

    if (! container || ! calendarTabButton) {
        return;
    }

    const endpoint = container.dataset.endpoint;
    const dayUrlBase = container.dataset.dayUrlBase;

    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    const statusLabels = {
        solved: 'Solved',
        open: 'Not solved yet',
        no_query: 'No challenge that day',
        other_game: 'Another game\'s challenge',
    };

    const statusClasses = {
        solved: 'bg-success',
        open: 'bg-warning text-dark',
        no_query: 'bg-secondary bg-opacity-25',
        other_game: 'bg-secondary bg-opacity-25',
    };

    const state = { year: new Date().getFullYear(), gameId: '' };
    const knownGames = new Map();
    let lastData = null;
    let loaded = false;

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function dayCellHtml(day, dateString, status) {
        const label = statusLabels[status] || 'Unavailable';
        const colorClass = statusClasses[status] || 'bg-secondary bg-opacity-10';
        const clickable = status === 'solved' || status === 'open';
        const tag = clickable ? 'a' : 'div';
        // data-challenge-day lets challenge-day.js swap the day in place.
        const href = clickable ? ' href="' + dayUrlBase + '/' + dateString + '" data-challenge-day="' + dateString + '"' : '';

        return '<' + tag + href + ' class="d-flex align-items-center justify-content-center rounded ' + colorClass + '" '
            + 'style="aspect-ratio:1;text-decoration:none;color:inherit;font-size:0.75rem;" title="' + label + '">' + day + '</' + tag + '>';
    }

    function monthHtml(year, month, days, dayGames) {
        const firstOfMonth = new Date(Date.UTC(year, month - 1, 1));
        const startWeekday = firstOfMonth.getUTCDay();
        const daysInMonth = new Date(Date.UTC(year, month, 0)).getUTCDate();

        let cells = '';
        for (let i = 0; i < startWeekday; i++) {
            cells += '<div></div>';
        }
        for (let day = 1; day <= daysInMonth; day++) {
            const dateString = year + '-' + pad(month) + '-' + pad(day);
            let status = days[dateString];
            if (state.gameId !== '' && (status === 'solved' || status === 'open') && String(dayGames[dateString]) !== state.gameId) {
                status = 'other_game';
            }
            cells += dayCellHtml(day, dateString, status);
        }

        return '<div class="col-12 col-sm-6 col-lg-4 col-xl-3 mb-4">'
            + '<div class="text-center mb-1">' + monthNames[month - 1] + '</div>'
            + '<div class="d-grid mb-1" style="grid-template-columns:repeat(7, 1fr);gap:2px;">'
            + weekdayNames.map((name) => '<div class="text-center text-white-50" style="font-size:0.65rem;">' + name.charAt(0) + '</div>').join('')
            + '</div>'
            + '<div class="d-grid" style="grid-template-columns:repeat(7, 1fr);gap:2px;">' + cells + '</div>'
            + '</div>';
    }

    function gameFilterHtml(dayGames) {
        const options = Array.from(knownGames.entries())
            .sort((a, b) => a[1].localeCompare(b[1]))
            .map(([id, name]) => '<option value="' + id + '"' + (id === state.gameId ? ' selected' : '') + '>' + escapeHtml(name) + '</option>')
            .join('');

        let summary = '';
        if (state.gameId !== '') {
            const count = Object.values(dayGames).filter((gameId) => String(gameId) === state.gameId).length;
            summary = '<span class="text-white-50 small ms-2">' + count + (count === 1 ? ' day' : ' days') + ' in ' + state.year + '</span>';
        }

        return '<div class="d-flex align-items-center flex-wrap gap-2 mb-3">'
            + '<label for="calendar-game-filter" class="small mb-0">Game</label>'
            + '<select id="calendar-game-filter" class="form-select form-select-sm w-auto">'
            + '<option value="">All games</option>' + options
            + '</select>'
            + summary
            + '</div>';
    }

    function render(data) {
        lastData = data;
        const days = data.days || {};
        const dayGames = data.day_games || {};
        const { year } = state;

        (data.games || []).forEach((game) => knownGames.set(String(game.id), game.name));

        let months = '';
        for (let month = 1; month <= 12; month++) {
            months += monthHtml(year, month, days, dayGames);
        }

        container.innerHTML = ''
            + '<div class="d-flex justify-content-between align-items-center mb-2">'
            + '<button type="button" class="btn btn-sm btn-outline-light" id="calendar-prev">&larr;</button>'
            + '<div>' + year + '</div>'
            + '<button type="button" class="btn btn-sm btn-outline-light" id="calendar-next">&rarr;</button>'
            + '</div>'
            + gameFilterHtml(dayGames)
            + '<div class="row">' + months + '</div>';

        document.getElementById('calendar-game-filter').addEventListener('change', (event) => {
            state.gameId = event.target.value;
            render(lastData);
        });
        document.getElementById('calendar-prev').addEventListener('click', () => changeYear(-1));
        document.getElementById('calendar-next').addEventListener('click', () => changeYear(1));
    }

    function changeYear(delta) {
        state.year += delta;
        load();
    }

    function load() {
        container.innerHTML = '<p class="text-white-50">Loading&hellip;</p>';
        fetch(endpoint + '?year=' + state.year)
            .then((response) => response.json())
            .then((data) => render(data))
            .catch(() => {
                container.innerHTML = '<p class="text-danger">Failed to load the calendar.</p>';
            });
    }

    calendarTabButton.addEventListener('shown.bs.tab', function () {
        if (loaded) {
            return;
        }
        loaded = true;
        load();
    });
});
