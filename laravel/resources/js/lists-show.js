function loadListPage(url, pushHistory) {
    const sidebar = document.getElementById('sidebarMenu');
    const description = document.getElementById('list-page-description');
    const body = document.getElementById('list-page-body');

    if (! sidebar || ! description || ! body) {
        return Promise.resolve();
    }

    return fetch(url, { headers: { Accept: 'application/json' } })
        .then(function (response) {
            if (! response.ok) {
                throw new Error('Request failed');
            }

            return response.json();
        })
        .then(function (data) {
            description.innerHTML = data.description;
            body.innerHTML = data.content;

            sidebar.querySelectorAll('a.nav-link').forEach(function (link) {
                link.classList.toggle('active', Number(link.dataset.pageId) === data.pageId);
            });

            if (pushHistory) {
                history.pushState({ pageId: data.pageId }, '', url);
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        })
        .catch(function () {
            window.location.href = url;
        });
}

document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebarMenu');

    if (! sidebar) {
        return;
    }

    let isLoading = false;

    sidebar.addEventListener('click', function (event) {
        const link = event.target.closest('a.nav-link');

        if (! link || isLoading) {
            return;
        }

        event.preventDefault();
        isLoading = true;

        loadListPage(link.href, true).finally(function () {
            isLoading = false;
        });
    });

    window.addEventListener('popstate', function () {
        loadListPage(window.location.href, false);
    });
});
