import 'bootstrap/dist/js/bootstrap.bundle.min.js';

window.returnColor = function (color) {
    document.getElementById('headcolor').value = color;
};

window.copytoclip = function (link) {
    const dummy = document.createElement('textarea');
    document.body.appendChild(dummy);
    dummy.value = link;
    dummy.select();
    document.execCommand('copy');
    document.body.removeChild(dummy);
};

window.playVideo = function (videoId) {
    const video = document.getElementById(videoId);
    video.src = video.dataset.src;
};

window.resetVideo = function (videoId) {
    const video = document.getElementById(videoId);
    const src = video.src.replace('?autoplay=1', '');
    video.src = '';
    video.dataset.src = src;
};

window.moveNumbers = function (num) {
    const textarea = document.getElementById('comboarea');
    textarea.value = textarea.value + (num === '>' ? ' > ' : num);
};

window.backspace = function () {
    const textarea = document.getElementById('comboarea');
    let txt = textarea.value;
    if (txt.length === 0) {
        return;
    }
    if (txt[txt.length - 1] === ' ') {
        txt = txt.substring(0, txt.length - 1);
    }
    while (txt.length > 0 && txt[txt.length - 1] !== ' ') {
        txt = txt.substring(0, txt.length - 1);
    }
    textarea.value = txt;
};

let showAllSecondaryResources = false;

window.filterSecondaryResources = function () {
    const select = document.querySelector('select[name="character_idcharacter"]');
    const characterId = select ? select.value : '';

    document.querySelectorAll('.secondary-resource-col').forEach((col) => {
        const restricted = col.dataset.characters;
        const visible = showAllSecondaryResources || !restricted || restricted.split(',').includes(characterId);
        col.style.display = visible ? '' : 'none';
    });
};

window.toggleSecondaryResources = function () {
    showAllSecondaryResources = !showAllSecondaryResources;

    const button = document.getElementById('toggle-secondary-resources');
    if (button) {
        button.textContent = showAllSecondaryResources ? 'Show relevant only' : 'Show all secondary resources';
    }

    window.filterSecondaryResources();
};

window.initSecondaryResources = function (forceShow) {
    showAllSecondaryResources = forceShow;

    const button = document.getElementById('toggle-secondary-resources');
    if (button) {
        button.textContent = showAllSecondaryResources ? 'Show relevant only' : 'Show all secondary resources';
    }

    window.filterSecondaryResources();
};

window.change_display = function () {
    const line = document.getElementById('combo_line');
    const text = document.getElementById('combo_text');
    const temp = line.innerHTML;
    line.innerHTML = text.innerHTML;
    text.innerHTML = temp;
};

window.showComboEdit = function () {
    document.getElementById('combo-view').style.display = 'none';
    document.getElementById('combo-edit-form').style.display = 'block';
};

window.cancelComboEdit = function () {
    location.reload();
};

window.addComboToList = function (button, listId, comboId) {
    if (button.disabled) {
        return;
    }

    const originalText = button.textContent.trim();
    button.disabled = true;
    button.textContent = 'Adding…';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    fetch(`/combos/${comboId}/lists/${listId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
    })
        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
        .then(({ ok, data }) => {
            if (! ok) {
                button.disabled = false;
                button.textContent = data.error || data.message || 'Could not add.';
                setTimeout(() => { button.textContent = originalText; }, 3000);
                return;
            }

            button.textContent = originalText + ' ✓';
        })
        .catch(() => {
            button.disabled = false;
            button.textContent = 'Could not add.';
            setTimeout(() => { button.textContent = originalText; }, 3000);
        });
};

window.toggleFavorite = function (button, comboId) {
    if (button.disabled) {
        return;
    }

    const wasFavorited = button.dataset.favorited === '1';
    const action = wasFavorited ? 'unfavorite' : 'favorite';
    const originalText = button.textContent.trim();

    button.disabled = true;
    button.textContent = wasFavorited ? 'Removing…' : 'Adding…';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    fetch(`/combos/${comboId}/${action}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
    })
        .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
        .then(({ ok, data }) => {
            button.disabled = false;

            if (! ok) {
                button.textContent = data.error || data.message || 'Could not update favorite.';
                setTimeout(() => { button.textContent = originalText; }, 3000);
                return;
            }

            const nowFavorited = ! wasFavorited;
            button.dataset.favorited = nowFavorited ? '1' : '0';
            button.classList.toggle('btn-warning', nowFavorited);
            button.classList.toggle('btn-outline-warning', ! nowFavorited);
            button.textContent = nowFavorited ? '★ Favorited' : '☆ Favorite';
        })
        .catch(() => {
            button.disabled = false;
            button.textContent = 'Could not update favorite.';
            setTimeout(() => { button.textContent = originalText; }, 3000);
        });
};

window.showDIV = function (divId) {
    const el = document.getElementById(divId);
    if (el.style.display === 'none') {
        el.style.display = 'block';
        window.playVideo('v' + divId);
    } else {
        el.style.display = 'none';
        window.resetVideo('v' + divId);
    }
};
