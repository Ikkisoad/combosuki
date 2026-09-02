import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import { Modal } from 'bootstrap';

/**
 * Site-wide replacement for window.confirm(), styled with the same
 * combosuki-main-reversed modal used elsewhere, since native browser
 * confirm() dialogs can't be themed. Returns a Promise<boolean> instead of
 * blocking synchronously, so callers that used to check `confirm(...)`
 * inline need to move the follow-up logic into a `.then()`/`await`.
 */
let confirmModal = null;
let resolveConfirm = null;

window.confirmDialog = function (message) {
    const modalEl = document.getElementById('global-confirm-modal');
    const messageEl = document.getElementById('global-confirm-modal-message');

    if (! modalEl || ! messageEl) {
        return Promise.resolve(window.confirm(message));
    }

    if (! confirmModal) {
        confirmModal = new Modal(modalEl);

        modalEl.querySelector('#global-confirm-modal-accept').addEventListener('click', () => {
            confirmModal.hide();
            resolveConfirm?.(true);
            resolveConfirm = null;
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            resolveConfirm?.(false);
            resolveConfirm = null;
        });
    }

    messageEl.textContent = message;

    return new Promise((resolve) => {
        resolveConfirm = resolve;
        confirmModal.show();
    });
};

/**
 * Forms marked data-confirm="message" get the modal above instead of a
 * native confirm() before they submit. The listener runs in the capture
 * phase so it can intercept before other submit handlers run.
 */
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (! (form instanceof HTMLFormElement) || ! form.dataset.confirm) {
        return;
    }

    if (form.dataset.confirmed === '1') {
        form.dataset.confirmed = '';
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    window.confirmDialog(form.dataset.confirm).then((ok) => {
        if (! ok) {
            return;
        }

        form.dataset.confirmed = '1';
        form.requestSubmit();
    });
}, true);

/**
 * Same idea as above, but for a single submit button inside a form that has
 * other submit buttons which shouldn't be confirmed (e.g. "Update" next to
 * "Delete") — data-confirm goes on the button instead of the form, and
 * requestSubmit(button) re-fires the click with that button as the
 * submitter so its name/value still reaches the server.
 */
document.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-confirm], input[type=submit][data-confirm]');

    if (! button || button.type !== 'submit' || ! button.form) {
        return;
    }

    if (button.dataset.confirmed === '1') {
        button.dataset.confirmed = '';
        return;
    }

    event.preventDefault();

    window.confirmDialog(button.dataset.confirm).then((ok) => {
        if (! ok) {
            return;
        }

        button.dataset.confirmed = '1';
        button.form.requestSubmit(button);
    });
});

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

/**
 * The combo form's character select has no server round-trip (see
 * filterSecondaryResources above), so a resource value's per-character alias
 * (e.g. a "Support" resource whose options are really named differently per
 * character) has to be swapped into the <option> labels client-side instead
 * of being rendered server-side.
 */
window.updateResourceValueAliases = function () {
    const aliasesEl = document.getElementById('resource-value-aliases');
    const characterSelect = document.querySelector('select[name="character_idcharacter"]');

    if (! aliasesEl || ! characterSelect) {
        return;
    }

    const aliases = JSON.parse(aliasesEl.textContent || '{}');
    const characterAliases = aliases[characterSelect.value] || {};

    document.querySelectorAll('select.resource-value-select option[data-default-label]').forEach((option) => {
        option.textContent = characterAliases[option.value] || option.dataset.defaultLabel;
    });
};

/**
 * The combo form's character select has no server round-trip (see
 * filterSecondaryResources above), so the "Other button names…" list can't
 * be filtered to the selected character's move aliases server-side.
 * Instead this reads every character's aliases (character-button-aliases-data,
 * shipped by ComboController::characterButtonAliasesByCharacter()) and
 * renders just the selected character's as buttons alongside the game-wide
 * ones already rendered server-side in #button-aliases.
 */
window.updateCharacterButtonAliases = function () {
    const aliasesEl = document.getElementById('character-button-aliases-data');
    const characterSelect = document.querySelector('select[name="character_idcharacter"]');
    const container = document.getElementById('character-button-alias-buttons');

    if (! aliasesEl || ! characterSelect || ! container) {
        return;
    }

    const aliases = JSON.parse(aliasesEl.textContent || '{}');
    const characterAliases = aliases[characterSelect.value] || [];

    container.innerHTML = '';

    characterAliases.forEach((alias) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm';
        button.style.marginLeft = '0.25em';
        button.style.marginBottom = '0.5em';
        button.style.backgroundColor = alias.color;
        button.textContent = alias.alias;
        button.addEventListener('click', () => window.moveNumbers(alias.buttonName));
        container.appendChild(button);
    });
};

/**
 * The combo notation display (#combo_display) has two independent toggles —
 * Display Method (rendered vs. raw text) and Remove/Show Aliases (as
 * submitted vs. every alias expanded to its real button name) — backed by up
 * to 4 pre-rendered <template> variants (combo_variant_{rendered,text}_
 * {aliased,dealiased}, see combos/show.blade.php). Both toggles just flip a
 * data-* flag on #combo_display and re-copy the matching template's markup
 * in, so the two combine freely instead of the previous approach of swapping
 * innerHTML between two elements, which only worked for one toggle at a time
 * and corrupted the other once both were used together.
 */
window.renderComboDisplay = function () {
    const display = document.getElementById('combo_display');

    if (! display) {
        return;
    }

    const variant = (display.dataset.textMode === '1' ? 'text' : 'rendered')
        + '_' + (display.dataset.noAlias === '1' ? 'dealiased' : 'aliased');

    const template = document.getElementById('combo_variant_' + variant);

    if (template) {
        display.innerHTML = template.innerHTML;
    }
};

window.change_display = function () {
    const display = document.getElementById('combo_display');
    display.dataset.textMode = display.dataset.textMode === '1' ? '0' : '1';
    window.renderComboDisplay();
};

window.toggleAliases = function () {
    const display = document.getElementById('combo_display');
    display.dataset.noAlias = display.dataset.noAlias === '1' ? '0' : '1';
    window.renderComboDisplay();

    const button = document.getElementById('toggle-aliases-btn');
    button.textContent = display.dataset.noAlias === '1' ? 'Show Aliases' : 'Remove Aliases';
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

window.copyQueryInto = function (select) {
    const sourceId = select.value;
    const targetForm = select.closest('form');
    if (! sourceId || ! targetForm) {
        return;
    }

    const sourceForm = document.querySelector(`form[data-query-id="${sourceId}"]`);
    if (! sourceForm) {
        return;
    }

    const nameCounts = {};

    sourceForm.querySelectorAll('input[name], select[name], textarea[name]').forEach((sourceField) => {
        if (sourceField.name === 'idquery' || sourceField.name === '_token') {
            return;
        }

        const index = nameCounts[sourceField.name] || 0;
        nameCounts[sourceField.name] = index + 1;

        const targetField = targetForm.querySelectorAll(`[name="${sourceField.name}"]`)[index];
        if (! targetField) {
            return;
        }

        if (sourceField.type === 'checkbox' || sourceField.type === 'radio') {
            targetField.checked = sourceField.checked;
            targetField.dispatchEvent(new Event('change'));
        } else if (sourceField.type === 'select-multiple') {
            const selectedValues = Array.from(sourceField.selectedOptions).map((option) => option.value);
            Array.from(targetField.options).forEach((option) => {
                option.selected = selectedValues.includes(option.value);
            });
        } else {
            targetField.value = sourceField.value;
        }
    });
};

window.clearMultiSelect = function (button) {
    const select = button.closest('.col-auto')?.querySelector('select[multiple]');
    if (! select) {
        return;
    }

    Array.from(select.options).forEach((option) => { option.selected = false; });
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
