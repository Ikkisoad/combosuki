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
});
