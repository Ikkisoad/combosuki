/**
 * Renders the challenge calendar's year view (12 month grids) client-side.
 * The container itself is present in the initial page render (see
 * challenge/show.blade.php) — only its data is lazy-loaded, deferred until
 * the Calendar tab is first shown, then re-fetched per year as the visitor
 * navigates.
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
    };

    const statusClasses = {
        solved: 'bg-success',
        open: 'bg-warning text-dark',
        no_query: 'bg-secondary bg-opacity-25',
    };

    const state = { year: new Date().getFullYear() };
    let loaded = false;

    function pad(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function dayCellHtml(day, dateString, status) {
        const label = statusLabels[status] || 'Unavailable';
        const colorClass = statusClasses[status] || 'bg-secondary bg-opacity-10';
        const clickable = status === 'solved' || status === 'open';
        const tag = clickable ? 'a' : 'div';
        const href = clickable ? ' href="' + dayUrlBase + '/' + dateString + '"' : '';

        return '<' + tag + href + ' class="d-flex align-items-center justify-content-center rounded ' + colorClass + '" '
            + 'style="aspect-ratio:1;text-decoration:none;color:inherit;font-size:0.75rem;" title="' + label + '">' + day + '</' + tag + '>';
    }

    function monthHtml(year, month, days) {
        const firstOfMonth = new Date(Date.UTC(year, month - 1, 1));
        const startWeekday = firstOfMonth.getUTCDay();
        const daysInMonth = new Date(Date.UTC(year, month, 0)).getUTCDate();

        let cells = '';
        for (let i = 0; i < startWeekday; i++) {
            cells += '<div></div>';
        }
        for (let day = 1; day <= daysInMonth; day++) {
            const dateString = year + '-' + pad(month) + '-' + pad(day);
            cells += dayCellHtml(day, dateString, days[dateString]);
        }

        return '<div class="col-12 col-sm-6 col-lg-4 col-xl-3 mb-4">'
            + '<div class="text-center mb-1">' + monthNames[month - 1] + '</div>'
            + '<div class="d-grid mb-1" style="grid-template-columns:repeat(7, 1fr);gap:2px;">'
            + weekdayNames.map((name) => '<div class="text-center text-white-50" style="font-size:0.65rem;">' + name.charAt(0) + '</div>').join('')
            + '</div>'
            + '<div class="d-grid" style="grid-template-columns:repeat(7, 1fr);gap:2px;">' + cells + '</div>'
            + '</div>';
    }

    function render(data) {
        const days = data.days || {};
        const { year } = state;

        let months = '';
        for (let month = 1; month <= 12; month++) {
            months += monthHtml(year, month, days);
        }

        container.innerHTML = ''
            + '<div class="d-flex justify-content-between align-items-center mb-2">'
            + '<button type="button" class="btn btn-sm btn-outline-light" id="calendar-prev">&larr;</button>'
            + '<div>' + year + '</div>'
            + '<button type="button" class="btn btn-sm btn-outline-light" id="calendar-next">&rarr;</button>'
            + '</div>'
            + '<div class="row">' + months + '</div>';

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
