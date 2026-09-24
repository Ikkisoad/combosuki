/**
 * Swaps the challenge page's day section (challenge/partials/day) in place
 * when the visitor moves between days — prev/next links, the date picker,
 * or a calendar cell — instead of reloading the whole page. Each day's
 * panel HTML is fetched from /challenge/{date}/panel once and cached for
 * the page's lifetime, the URL is kept in sync via history.pushState, and
 * back/forward re-render from that cache. Every link still has a real href,
 * so a failed fetch (or a modifier-click) falls back to normal navigation.
 */
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('challenge-day');

    if (! container) {
        return;
    }

    const base = container.dataset.endpointBase;
    const cache = {};
    let pending = null;

    function currentPanel() {
        return container.firstElementChild;
    }

    function dayUrl(date) {
        return base + '/' + date;
    }

    function fetchPanel(date) {
        if (cache[date]) {
            return Promise.resolve(cache[date]);
        }

        return fetch(dayUrl(date) + '/panel', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => {
                if (! response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then((html) => {
                cache[date] = html;
                return html;
            });
    }

    function render(html) {
        container.innerHTML = html;
        const panel = currentPanel();
        if (panel && panel.dataset.title) {
            document.title = panel.dataset.title;
        }
    }

    function show(date, pushHistory) {
        const panel = currentPanel();
        if (panel && panel.dataset.date === date) {
            // Drop any in-flight fetch for a day the visitor has moved away from.
            pending = null;
            container.style.opacity = '';
            return;
        }

        pending = date;
        container.style.opacity = '0.5';

        fetchPanel(date)
            .then((html) => {
                // A slower response for an earlier click must not overwrite
                // the day the visitor has since moved on to.
                if (pending !== date) {
                    return;
                }
                render(html);
                if (pushHistory) {
                    history.pushState({ challengeDate: date }, '', dayUrl(date));
                }
            })
            .catch(() => {
                window.location.href = dayUrl(date);
            })
            .finally(() => {
                if (pending === date) {
                    container.style.opacity = '';
                    pending = null;
                }
            });
    }

    // Seed the cache and history entry with the server-rendered day, so
    // navigating back to it doesn't refetch.
    const initial = currentPanel();
    if (initial && initial.dataset.date) {
        cache[initial.dataset.date] = container.innerHTML;
        history.replaceState({ challengeDate: initial.dataset.date }, '');
    }

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a[data-challenge-day]');
        if (! link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        event.preventDefault();

        // Calendar cells sit below the fold — bring the swapped day into view.
        if (! container.contains(link)) {
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        show(link.dataset.challengeDay, true);
    });

    container.addEventListener('change', function (event) {
        if (event.target.id === 'challenge-date-picker' && event.target.value) {
            show(event.target.value, true);
        }
    });

    window.addEventListener('popstate', function (event) {
        if (event.state && event.state.challengeDate) {
            show(event.state.challengeDate, false);
        }
    });
});
