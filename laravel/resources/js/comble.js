function fillOptions(select, items, valueKey, labelKey, placeholder) {
    select.innerHTML = '';

    const placeholderOption = document.createElement('option');
    placeholderOption.value = '';
    placeholderOption.textContent = placeholder;
    select.appendChild(placeholderOption);

    items.forEach((item) => {
        const option = document.createElement('option');
        option.value = item[valueKey];
        option.textContent = item[labelKey];
        select.appendChild(option);
    });
}

function initGuessForm() {
    const catalogEl = document.getElementById('comble-catalog');
    const gameSelect = document.getElementById('comble-game');
    const characterSelect = document.getElementById('comble-character');
    const typeSelect = document.getElementById('comble-type');

    if (! catalogEl || ! gameSelect || ! characterSelect || ! typeSelect) {
        return;
    }

    const catalog = JSON.parse(catalogEl.textContent);

    fillOptions(gameSelect, catalog.games, 'id', 'name', 'Choose a game…');

    gameSelect.addEventListener('change', function () {
        const characters = catalog.charactersByGame[gameSelect.value] || [];
        const types = catalog.typesByGame[gameSelect.value] || [];

        if (gameSelect.value === '') {
            fillOptions(characterSelect, [], 'id', 'name', 'Choose a game first…');
            fillOptions(typeSelect, [], 'id', 'title', 'Choose a game first…');
            characterSelect.disabled = true;
            typeSelect.disabled = true;
            return;
        }

        fillOptions(characterSelect, characters, 'id', 'name', 'Choose a character…');
        fillOptions(typeSelect, types, 'id', 'title', 'Choose a type…');
        characterSelect.disabled = false;
        typeSelect.disabled = false;

        if (characterSelect.dataset.sticky) {
            characterSelect.value = characterSelect.dataset.sticky;
        }

        if (typeSelect.dataset.sticky) {
            typeSelect.value = typeSelect.dataset.sticky;
        }

        // A correct type guess only matches by id within the game it was
        // guessed from — every game defines its own row per category, so
        // switching games above never finds that id among the new options
        // and the select above silently falls back to the placeholder. The
        // category name itself ("Combo", "Okizeme", ...) is what's actually
        // correct (see CombleGuessEvaluator::sameTypeTitle()), so re-select
        // by matching title text instead whenever the id didn't stick.
        if (typeSelect.value === '' && typeSelect.dataset.stickyTitle) {
            const stickyTitle = typeSelect.dataset.stickyTitle.trim().toLowerCase();
            const match = Array.from(typeSelect.options).find(
                (option) => option.textContent.trim().toLowerCase() === stickyTitle
            );

            if (match) {
                typeSelect.value = match.value;
            }
        }
    });

    if (gameSelect.dataset.sticky) {
        gameSelect.value = gameSelect.dataset.sticky;
        gameSelect.dispatchEvent(new Event('change'));
    }
}

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

function copyShareText(shareBtn) {
    const feedback = document.getElementById('comble-share-feedback');
    const text = shareBtn.dataset.shareText;

    const showFeedback = function (success) {
        if (! feedback) return;
        feedback.textContent = success ? 'Copied!' : 'Could not copy to clipboard.';
        feedback.className = success ? 'small text-success' : 'small text-danger';
        feedback.style.display = 'inline';
    };

    if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
        navigator.clipboard.writeText(text)
            .then(function () { showFeedback(true); })
            .catch(function () { showFeedback(legacyCopy(text)); });
        return;
    }

    showFeedback(legacyCopy(text));
}

function showGuessError(message) {
    const errorEl = document.getElementById('comble-guess-error');

    if (! errorEl) {
        return;
    }

    errorEl.textContent = message;
    errorEl.style.display = message ? 'block' : 'none';
}

/**
 * Submits a guess via fetch instead of a normal form POST, so a correct or
 * wrong guess updates the reveal/table/stats in place instead of reloading
 * the whole page. Falls back to a normal submit (full page reload) if fetch
 * throws before it can even reach the network, e.g. in very old browsers.
 */
function submitGuessForm(form) {
    showGuessError('');

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : null;

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Guessing…';
    }

    fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
    })
        .then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, status: response.status, data: data };
            });
        })
        .then(function (result) {
            if (result.ok) {
                const container = document.getElementById('comble-game-state');

                if (container) {
                    container.outerHTML = result.data.html;
                }

                initGuessForm();

                return;
            }

            if (result.status === 422 && result.data.errors) {
                showGuessError(Object.values(result.data.errors).flat().join(' '));

                return;
            }

            showGuessError(result.data.error || result.data.message || 'Something went wrong. Please try again.');
        })
        .catch(function () {
            showGuessError('Network error. Please try again.');
        })
        .finally(function () {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
}

document.addEventListener('DOMContentLoaded', function () {
    initGuessForm();

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('#comble-guess-form');

        if (! form) {
            return;
        }

        event.preventDefault();
        submitGuessForm(form);
    });

    document.addEventListener('click', function (event) {
        const shareBtn = event.target.closest('#comble-share-btn');

        if (shareBtn) {
            copyShareText(shareBtn);
        }
    });
});
