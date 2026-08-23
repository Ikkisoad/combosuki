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

function initShareButton() {
    const shareBtn = document.getElementById('comble-share-btn');
    const feedback = document.getElementById('comble-share-feedback');

    if (! shareBtn) {
        return;
    }

    const showFeedback = function (success) {
        if (! feedback) return;
        feedback.textContent = success ? 'Copied!' : 'Could not copy to clipboard.';
        feedback.className = success ? 'small text-success' : 'small text-danger';
        feedback.style.display = 'inline';
    };

    shareBtn.addEventListener('click', function () {
        const text = shareBtn.dataset.shareText;

        if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
            navigator.clipboard.writeText(text)
                .then(function () { showFeedback(true); })
                .catch(function () { showFeedback(legacyCopy(text)); });
            return;
        }

        showFeedback(legacyCopy(text));
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initGuessForm();
    initShareButton();
});
