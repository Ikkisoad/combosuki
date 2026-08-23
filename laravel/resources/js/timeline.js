function legacyCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    let copied = false;
    try {
        copied = document.execCommand('copy');
    } catch (e) {
        copied = false;
    }

    document.body.removeChild(textarea);

    return copied;
}

function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
        return navigator.clipboard.writeText(text).catch(() => legacyCopy(text));
    }

    return Promise.resolve(legacyCopy(text));
}

function appendHtml(container, html) {
    const template = document.createElement('template');
    template.innerHTML = html;

    template.content.querySelectorAll('script').forEach(function (oldScript) {
        const newScript = document.createElement('script');
        Array.from(oldScript.attributes).forEach(function (attr) {
            newScript.setAttribute(attr.name, attr.value);
        });
        newScript.textContent = oldScript.textContent;
        oldScript.replaceWith(newScript);
    });

    container.appendChild(template.content);
}

function initTimelineLoadMore() {
    const list = document.getElementById('timeline-list');
    const button = document.getElementById('timeline-load-more');

    if (! list || ! button) {
        return;
    }

    let nextPageUrl = list.dataset.nextPageUrl || null;
    let isLoading = false;

    button.addEventListener('click', function () {
        if (isLoading || ! nextPageUrl) {
            return;
        }

        isLoading = true;
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Loading…';

        fetch(nextPageUrl, { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                appendHtml(list, data.html);
                nextPageUrl = data.nextPageUrl;
                isLoading = false;
                button.disabled = false;
                button.textContent = originalText;

                if (! nextPageUrl) {
                    button.style.display = 'none';
                }
            })
            .catch(function () {
                isLoading = false;
                button.disabled = false;
                button.textContent = originalText;
            });
    });
}

function initTimelineBackToTop() {
    const button = document.getElementById('timeline-back-to-top');

    if (! button) {
        return;
    }

    button.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-share-link]');

        if (! button) {
            return;
        }

        const link = button.dataset.shareLink;
        const originalText = button.textContent;

        Promise.resolve(copyText(link)).then(function (result) {
            button.textContent = result === false ? 'Could not copy' : 'Copied!';
            setTimeout(function () { button.textContent = originalText; }, 1500);
        });
    });

    initTimelineLoadMore();
    initTimelineBackToTop();
});
