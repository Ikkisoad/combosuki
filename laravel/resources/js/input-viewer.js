const STORAGE_KEY = 'combosuki:input-viewer:v1';
const MAX_IMAGE_SIZE = 128;
const MACRO_CONTAINER_SIZE = 28;

// The 2-image case is the reference size (kept as-is); each image beyond
// that shrinks a bit further so more of them don't just pile on top of one
// another inside the same fixed-size composite.
const MACRO_IMAGE_SIZES = { 2: 18, 3: 15, 4: 13 };
const MACRO_IMAGE_SIZE_MIN = 11;

function macroImageSize(count) {
    return MACRO_IMAGE_SIZES[count] || MACRO_IMAGE_SIZE_MIN;
}

// Where each image in a macro leans within the (fixed-size) composite —
// 1st top-left, 2nd bottom-right, 3rd top-right, 4th bottom-left. Cycles for
// macros with more than 4 images.
const MACRO_CORNERS = [
    { left: 0, top: 0 },
    { left: 1, top: 1 },
    { left: 1, top: 0 },
    { left: 0, top: 1 },
];

// With exactly 3 images, the "2nd" (bottom-right) slot moves to dead center
// instead — top-left / center / top-right reads more like a fan than three
// corners of a box with one left empty.
const MACRO_CORNERS_TRIO = [
    { left: 0, top: 0 },
    { left: 0.5, top: 0.5 },
    { left: 1, top: 0 },
];

const DEFAULT_SETTINGS = {
    pollingFPS: 60,
    chargeThreshold: 30,
    hideThreshold: 120,
    historyLimit: 30,
    directionSource: 'dpad',
    stickDeadzone: 0.5,
};

const DIRECTIONS = ['up', 'upright', 'right', 'downright', 'down', 'downleft', 'left', 'upleft', 'idle'];

const DIRECTION_LABELS = {
    up: 'Up',
    upright: 'Up-Right',
    right: 'Right',
    downright: 'Down-Right',
    down: 'Down',
    downleft: 'Down-Left',
    left: 'Left',
    upleft: 'Up-Left',
    idle: 'Neutral',
};

// Hat-switch axis[9] value -> direction, for fight-stick/hitbox devices that
// report the digital stick as a single quantized axis instead of 4 buttons.
const HAT_DIRECTIONS = {
    '-1': 'up',
    '-0.71429': 'upright',
    '-0.42857': 'right',
    '-0.14286': 'downright',
    '0.14286': 'down',
    '0.42857': 'downleft',
    '0.71429': 'left',
    '1': 'upleft',
};

const DIRECTION_PARTS = {
    up: ['up'],
    down: ['down'],
    left: ['left'],
    right: ['right'],
    upleft: ['up', 'left'],
    upright: ['up', 'right'],
    downleft: ['down', 'left'],
    downright: ['down', 'right'],
    idle: [],
};

function loadStore() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return { profiles: {}, settings: { ...DEFAULT_SETTINGS }, lastGamepadId: null };
        const parsed = JSON.parse(raw);
        return {
            profiles: parsed.profiles || {},
            settings: { ...DEFAULT_SETTINGS, ...(parsed.settings || {}) },
            lastGamepadId: parsed.lastGamepadId || null,
        };
    } catch {
        return { profiles: {}, settings: { ...DEFAULT_SETTINGS }, lastGamepadId: null };
    }
}

function saveStore(store) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(store));
    } catch {
        // Quota exceeded or storage unavailable — mappings simply won't persist this session.
    }
}

function getProfile(store, gamepadId) {
    if (!store.profiles[gamepadId]) {
        store.profiles[gamepadId] = { buttons: {}, directions: {} };
    }
    return store.profiles[gamepadId];
}

function getSlotValue(profile, kind, key) {
    return kind === 'direction' ? profile.directions[key] : profile.buttons[key];
}

function setSlotValue(store, profile, kind, key, value) {
    const bucket = kind === 'direction' ? profile.directions : profile.buttons;
    if (value === undefined) {
        delete bucket[key];
    } else {
        bucket[key] = value;
    }
    saveStore(store);
}

function isMacro(value) {
    return !!value && typeof value === 'object' && Array.isArray(value.macro);
}

// Only plain (already-uploaded) images can be combined into a macro — this
// keeps macro resolution a single flat lookup instead of having to recurse
// into macros-of-macros.
function collectImageSources(profile, excludeKind, excludeKey) {
    const sources = [];

    DIRECTIONS.forEach((key) => {
        const value = profile.directions[key];
        if (typeof value === 'string' && !(excludeKind === 'direction' && excludeKey === key)) {
            sources.push({ kind: 'direction', key, label: DIRECTION_LABELS[key] || key, src: value });
        }
    });

    Object.keys(profile.buttons).forEach((key) => {
        const value = profile.buttons[key];
        if (typeof value === 'string' && !(excludeKind === 'button' && excludeKey === key)) {
            sources.push({ kind: 'button', key, label: `Button ${key}`, src: value });
        }
    });

    return sources;
}

function roundAxis(value) {
    return parseFloat(value.toFixed(5));
}

function directionsShareCharge(lastDir, newDir) {
    if (lastDir === newDir) return true;
    const lastParts = DIRECTION_PARTS[lastDir] || [];
    const newParts = DIRECTION_PARTS[newDir] || [];
    if (newParts.length > 1) return false;
    return lastParts.some((p) => newParts.includes(p)) || newParts.some((p) => lastParts.includes(p));
}

function resizeImageFile(file, maxSize = MAX_IMAGE_SIZE) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onerror = () => reject(reader.error);
        reader.onload = () => {
            const img = new Image();
            img.onerror = () => reject(new Error('Could not read image file'));
            img.onload = () => {
                const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
                const w = Math.max(1, Math.round(img.width * scale));
                const h = Math.max(1, Math.round(img.height * scale));
                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                resolve(canvas.toDataURL('image/png'));
            };
            img.src = reader.result;
        };
        reader.readAsDataURL(file);
    });
}

// A macro (e.g. an A+B macro button) renders as its source images fanned
// diagonally, each one nudged a few pixels further and layered in front of
// the last — so it reads as "more than one input" while still occupying
// roughly the footprint of a single icon instead of a full row/grid.
function buildMacroIcon(refs, label, profile) {
    const wrapper = document.createElement('div');
    wrapper.className = 'macro-icon';
    wrapper.title = label;

    const imgs = refs
        .map((ref) => getSlotValue(profile, ref.kind, ref.key))
        .filter((src) => typeof src === 'string');

    if (imgs.length === 0) {
        wrapper.classList.add('input-chip');
        wrapper.textContent = label;
        return wrapper;
    }

    wrapper.style.width = `${MACRO_CONTAINER_SIZE}px`;
    wrapper.style.height = `${MACRO_CONTAINER_SIZE}px`;

    const imageSize = macroImageSize(imgs.length);
    const maxOffset = MACRO_CONTAINER_SIZE - imageSize;

    const corners = imgs.length === 3 ? MACRO_CORNERS_TRIO : MACRO_CORNERS;

    imgs.forEach((src, i) => {
        const img = document.createElement('img');
        img.src = src;
        img.alt = label;
        img.style.width = `${imageSize}px`;
        img.style.height = `${imageSize}px`;
        const corner = corners[i % corners.length];
        img.style.left = `${corner.left * maxOffset}px`;
        img.style.top = `${corner.top * maxOffset}px`;
        img.style.zIndex = String(i + 1);
        wrapper.appendChild(img);
    });

    return wrapper;
}

function buildIconNode(value, label, profile) {
    if (typeof value === 'string') {
        const img = document.createElement('img');
        img.src = value;
        img.alt = label;
        return img;
    }
    if (isMacro(value)) {
        return buildMacroIcon(value.macro, label, profile);
    }
    const chip = document.createElement('span');
    chip.className = 'input-chip';
    chip.textContent = label;
    return chip;
}

function renderPreview(previewEl, value, label, profile) {
    previewEl.innerHTML = '';
    if (typeof value === 'string') {
        const img = document.createElement('img');
        img.src = value;
        previewEl.appendChild(img);
    } else if (isMacro(value)) {
        previewEl.appendChild(buildMacroIcon(value.macro, label, profile));
    }
}

function initInputViewer() {
    const root = document.getElementById('input-viewer');
    if (!root) return;

    const store = loadStore();

    const historyEl = document.getElementById('history');
    const watermarkEl = document.getElementById('watermark');
    const panelEl = document.getElementById('config-panel');
    const toggleTab = document.getElementById('panel-toggle');
    const gamepadSelect = document.getElementById('gamepad-selector');
    const buttonRowsEl = document.getElementById('button-mapping-rows');
    const directionRowsEl = document.getElementById('direction-mapping-rows');
    const resetButton = document.getElementById('reset-mappings');
    const fpsInput = document.getElementById('setting-fps');
    const chargeInput = document.getElementById('setting-charge');
    const hideInput = document.getElementById('setting-hide');
    const historyLimitInput = document.getElementById('setting-history-limit');
    const directionSourceSelect = document.getElementById('direction-source');
    const deadzoneInput = document.getElementById('setting-deadzone');

    let currentGamepadIndex = null;
    let currentGamepadId = null;
    let panelHidden = false;
    let anyButtonHeldFrames = 0;

    let heldDirection = 'idle';
    let heldDirectionFrames = 0;
    let lastSignature = '';
    let lastEntry = null;
    let frameCount = 1;
    let pollingTimer = null;

    function currentProfile() {
        return currentGamepadId ? getProfile(store, currentGamepadId) : null;
    }

    function pollingInterval() {
        return 1000 / Math.max(1, store.settings.pollingFPS);
    }

    function applyFadeState() {
        panelEl.classList.toggle('faded', panelHidden);
        watermarkEl.classList.toggle('faded', panelHidden);
        toggleTab.classList.toggle('tab-hidden', panelHidden);
    }

    function iconElement(kind, key, label) {
        const profile = currentProfile();
        const value = profile ? getSlotValue(profile, kind, key) : null;
        return buildIconNode(value, label, profile);
    }

    function addInputToHistory(direction, buttons) {
        const entry = document.createElement('div');
        entry.className = 'input-entry';

        const counter = document.createElement('div');
        counter.className = 'frame-counter';
        counter.textContent = '1';
        entry.appendChild(counter);

        const dirIcon = iconElement('direction', direction, DIRECTION_LABELS[direction] || direction);
        if (direction !== 'idle' && heldDirectionFrames >= store.settings.chargeThreshold) {
            dirIcon.classList.add('glow');
        }
        entry.appendChild(dirIcon);

        buttons.forEach((index) => {
            entry.appendChild(iconElement('button', String(index), `B${index}`));
        });

        historyEl.appendChild(entry);
        while (historyEl.children.length > store.settings.historyLimit) {
            historyEl.removeChild(historyEl.children[0]);
        }

        return entry;
    }

    function combineDirections(up, down, left, right) {
        if (up && right) return 'upright';
        if (up && left) return 'upleft';
        if (down && right) return 'downright';
        if (down && left) return 'downleft';
        if (up) return 'up';
        if (down) return 'down';
        if (left) return 'left';
        if (right) return 'right';
        return 'idle';
    }

    function directionFromDpadButtons(gp) {
        return combineDirections(gp.buttons[12]?.pressed, gp.buttons[13]?.pressed, gp.buttons[14]?.pressed, gp.buttons[15]?.pressed);
    }

    function directionFromStick(x, y) {
        if (x === undefined || y === undefined) return 'idle';
        const deadzone = store.settings.stickDeadzone;
        return combineDirections(y < -deadzone, y > deadzone, x < -deadzone, x > deadzone);
    }

    // Whether the gamepad's d-pad buttons (12-15) are the ones supplying
    // direction right now — if so they're consumed by getDirection() and
    // shouldn't also show up as generic, individually-mappable buttons.
    function dpadButtonIndicesActive(gp) {
        if (store.settings.directionSource !== 'dpad') return false;
        return gp.axes[9] === undefined && gp.buttons.length > 15;
    }

    function getDirection(gp) {
        const source = store.settings.directionSource;

        if (source === 'leftStick') return directionFromStick(gp.axes[0], gp.axes[1]);
        if (source === 'rightStick') return directionFromStick(gp.axes[2], gp.axes[3]);

        // 'dpad' (default): a hat-switch axis, common on fight sticks/hitboxes,
        // takes priority when present; otherwise fall back to the standard
        // mapping's d-pad buttons.
        if (gp.axes[9] !== undefined) {
            const rounded = roundAxis(gp.axes[9]);
            return HAT_DIRECTIONS[String(rounded)] || 'idle';
        }
        return directionFromDpadButtons(gp);
    }

    function pollGamepad() {
        const gamepads = navigator.getGamepads();
        const gp = currentGamepadIndex !== null ? gamepads[currentGamepadIndex] : null;

        if (!gp) {
            scheduleNextPoll();
            return;
        }

        const excludeDpadButtons = dpadButtonIndicesActive(gp);
        const direction = getDirection(gp);

        if (directionsShareCharge(heldDirection, direction)) {
            heldDirectionFrames++;
            if (heldDirection !== direction) heldDirection = direction;
        } else {
            heldDirection = direction;
            heldDirectionFrames = 1;
        }

        const buttonIndices = [];
        for (let i = 0; i < gp.buttons.length; i++) {
            if (excludeDpadButtons && i >= 12 && i <= 15) continue;
            if (gp.buttons[i]?.pressed) buttonIndices.push(i);
        }

        const signature = JSON.stringify({ direction, buttonIndices: [...buttonIndices].sort((a, b) => a - b) });

        if (signature === lastSignature && lastEntry) {
            frameCount++;
            const counter = lastEntry.querySelector('.frame-counter');
            if (counter) counter.textContent = frameCount;

            const dirIcon = lastEntry.children[1];
            if (dirIcon && direction !== 'idle') {
                dirIcon.classList.toggle('glow', heldDirectionFrames >= store.settings.chargeThreshold);
            }
        } else {
            frameCount = 1;
            lastSignature = signature;
            lastEntry = addInputToHistory(direction, buttonIndices);
        }

        const anyHeld = buttonIndices.length > 0 || direction !== 'idle';
        if (anyHeld) {
            anyButtonHeldFrames++;
            if (anyButtonHeldFrames >= store.settings.hideThreshold && !panelHidden) {
                panelHidden = true;
                applyFadeState();
            }
        } else {
            anyButtonHeldFrames = 0;
        }

        scheduleNextPoll();
    }

    function scheduleNextPoll() {
        pollingTimer = setTimeout(pollGamepad, pollingInterval());
    }

    function restartPollingLoop() {
        clearTimeout(pollingTimer);
        scheduleNextPoll();
    }

    function refreshGamepadList() {
        const gamepads = navigator.getGamepads();
        gamepadSelect.innerHTML = '<option value="">Select Controller</option>';
        let matchedIndex = null;
        for (let i = 0; i < gamepads.length; i++) {
            const gp = gamepads[i];
            if (!gp) continue;
            const option = document.createElement('option');
            option.value = String(i);
            option.textContent = `${i}: ${gp.id}`;
            if (store.lastGamepadId && gp.id === store.lastGamepadId) {
                option.selected = true;
                matchedIndex = i;
            }
            gamepadSelect.appendChild(option);
        }
        if (matchedIndex !== null) {
            selectGamepad(matchedIndex, gamepads[matchedIndex]);
        }
    }

    function selectGamepad(index, gp) {
        currentGamepadIndex = index;
        currentGamepadId = gp.id;
        store.lastGamepadId = gp.id;
        saveStore(store);
        renderMappingRows(gp);
    }

    function renderMappingRows(gp) {
        const profile = getProfile(store, gp.id);
        const excludeDpadButtons = dpadButtonIndicesActive(gp);

        directionRowsEl.innerHTML = '';
        DIRECTIONS.forEach((dir) => {
            directionRowsEl.appendChild(buildMappingRow({ kind: 'direction', key: dir, label: DIRECTION_LABELS[dir], profile, gp }));
        });

        buttonRowsEl.innerHTML = '';
        for (let i = 0; i < gp.buttons.length; i++) {
            if (excludeDpadButtons && i >= 12 && i <= 15) continue;
            const key = String(i);
            buttonRowsEl.appendChild(buildMappingRow({ kind: 'button', key, label: `Button ${key}`, profile, gp }));
        }
    }

    function buildMappingRow({ kind, key, label, profile, gp }) {
        const row = document.createElement('div');
        row.className = 'mapping-row';

        const topLine = document.createElement('div');
        topLine.className = 'mapping-row-top';

        const preview = document.createElement('div');
        preview.className = 'mapping-preview';
        renderPreview(preview, getSlotValue(profile, kind, key), label, profile);
        topLine.appendChild(preview);

        const labelEl = document.createElement('span');
        labelEl.className = 'mapping-label';
        labelEl.textContent = label;
        topLine.appendChild(labelEl);

        const actions = document.createElement('div');
        actions.className = 'mapping-actions';

        const combineBtn = document.createElement('button');
        combineBtn.type = 'button';
        combineBtn.className = 'btn btn-sm btn-outline-light mapping-combine';
        combineBtn.textContent = 'Combine…';
        actions.appendChild(combineBtn);

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn btn-sm btn-outline-light mapping-clear';
        clearBtn.textContent = 'Clear';
        clearBtn.addEventListener('click', () => {
            setSlotValue(store, profile, kind, key, undefined);
            renderMappingRows(gp);
        });
        actions.appendChild(clearBtn);

        topLine.appendChild(actions);
        row.appendChild(topLine);

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.className = 'mapping-file form-control form-control-sm';
        fileInput.addEventListener('change', async () => {
            const file = fileInput.files && fileInput.files[0];
            if (!file) return;
            const dataUrl = await resizeImageFile(file);
            setSlotValue(store, profile, kind, key, dataUrl);
            renderMappingRows(gp);
        });
        row.appendChild(fileInput);

        const picker = buildMacroPicker({ kind, key, label, profile, gp });
        row.appendChild(picker);

        combineBtn.addEventListener('click', () => {
            picker.hidden = !picker.hidden;
        });

        return row;
    }

    // Lets a slot (e.g. a macro button like A+B on a fight stick) be set to
    // several already-uploaded images at once instead of a single upload —
    // see buildMacroIcon for how that's rendered as a compact diagonal fan.
    function buildMacroPicker({ kind, key, label, profile, gp }) {
        const wrap = document.createElement('div');
        wrap.className = 'macro-picker';
        wrap.hidden = true;

        const sources = collectImageSources(profile, kind, key);

        if (sources.length < 2) {
            wrap.classList.add('macro-picker-empty');
            wrap.textContent = 'Upload at least two other images before you can combine them into a macro.';
            return wrap;
        }

        const title = document.createElement('div');
        title.className = 'macro-picker-title';
        title.textContent = `Combine images for "${label}" (pick 2 or more):`;
        wrap.appendChild(title);

        const currentValue = getSlotValue(profile, kind, key);
        const currentRefs = isMacro(currentValue) ? currentValue.macro : [];

        const list = document.createElement('div');
        list.className = 'macro-picker-list';

        sources.forEach((source) => {
            const option = document.createElement('label');
            option.className = 'macro-picker-option';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = `${source.kind}:${source.key}`;
            checkbox.checked = currentRefs.some((r) => r.kind === source.kind && r.key === source.key);
            option.appendChild(checkbox);

            const thumb = document.createElement('img');
            thumb.src = source.src;
            thumb.className = 'macro-picker-thumb';
            option.appendChild(thumb);

            const text = document.createElement('span');
            text.textContent = source.label;
            option.appendChild(text);

            list.appendChild(option);
        });
        wrap.appendChild(list);

        const actions = document.createElement('div');
        actions.className = 'macro-picker-actions';

        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'btn btn-sm btn-combosuki';
        saveBtn.textContent = 'Save macro';
        saveBtn.addEventListener('click', () => {
            const checked = Array.from(list.querySelectorAll('input[type=checkbox]:checked')).map((cb) => {
                const [refKind, refKey] = cb.value.split(':');
                return { kind: refKind, key: refKey };
            });
            if (checked.length < 2) {
                alert('Pick at least two images to combine into a macro.');
                return;
            }
            setSlotValue(store, profile, kind, key, { macro: checked });
            renderMappingRows(gp);
        });
        actions.appendChild(saveBtn);

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-sm btn-outline-light';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.addEventListener('click', () => {
            wrap.hidden = true;
        });
        actions.appendChild(cancelBtn);

        wrap.appendChild(actions);

        return wrap;
    }

    gamepadSelect.addEventListener('change', () => {
        const index = parseInt(gamepadSelect.value, 10);
        const gamepads = navigator.getGamepads();
        if (!isNaN(index) && gamepads[index]) {
            selectGamepad(index, gamepads[index]);
        }
    });

    resetButton.addEventListener('click', () => {
        if (!currentGamepadId) return;
        if (!confirm('Clear all uploaded images for this controller?')) return;
        delete store.profiles[currentGamepadId];
        saveStore(store);
        const gamepads = navigator.getGamepads();
        if (currentGamepadIndex !== null && gamepads[currentGamepadIndex]) {
            renderMappingRows(gamepads[currentGamepadIndex]);
        }
    });

    function wireSetting(input, key, { min = 1 } = {}) {
        input.value = store.settings[key];
        input.addEventListener('change', () => {
            const value = parseInt(input.value, 10);
            if (isNaN(value) || value < min) return;
            store.settings[key] = value;
            saveStore(store);
            if (key === 'pollingFPS') restartPollingLoop();
        });
    }

    wireSetting(fpsInput, 'pollingFPS');
    wireSetting(chargeInput, 'chargeThreshold');
    wireSetting(hideInput, 'hideThreshold');
    wireSetting(historyLimitInput, 'historyLimit');

    directionSourceSelect.value = store.settings.directionSource;
    directionSourceSelect.addEventListener('change', () => {
        store.settings.directionSource = directionSourceSelect.value;
        saveStore(store);
        // Switching source changes which button indices (12-15) count as
        // the d-pad vs. plain mappable buttons — re-render to match.
        const gamepads = navigator.getGamepads();
        if (currentGamepadIndex !== null && gamepads[currentGamepadIndex]) {
            renderMappingRows(gamepads[currentGamepadIndex]);
        }
    });

    deadzoneInput.value = store.settings.stickDeadzone;
    deadzoneInput.addEventListener('change', () => {
        const value = parseFloat(deadzoneInput.value);
        if (isNaN(value) || value <= 0 || value >= 1) return;
        store.settings.stickDeadzone = value;
        saveStore(store);
    });

    toggleTab.addEventListener('click', () => {
        panelHidden = !panelHidden;
        applyFadeState();
    });

    window.addEventListener('gamepadconnected', refreshGamepadList);
    window.addEventListener('gamepaddisconnected', refreshGamepadList);

    refreshGamepadList();
    pollGamepad();
}

document.addEventListener('DOMContentLoaded', initInputViewer);
