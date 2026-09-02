const STORAGE_KEY = 'combosuki:input-viewer:v1';
const MAX_IMAGE_SIZE = 128;

const DEFAULT_SETTINGS = {
    pollingFPS: 60,
    chargeThreshold: 5,
    hideThreshold: 120,
    historyLimit: 30,
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
        const src = profile ? (kind === 'direction' ? profile.directions[key] : profile.buttons[key]) : null;
        if (src) {
            const img = document.createElement('img');
            img.src = src;
            img.alt = label;
            return img;
        }
        const chip = document.createElement('span');
        chip.className = 'input-chip';
        chip.textContent = label;
        return chip;
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

    function getDirection(gp, dpadIndices) {
        if (gp.axes[9] !== undefined) {
            const rounded = roundAxis(gp.axes[9]);
            return HAT_DIRECTIONS[String(rounded)] || 'idle';
        }
        if (dpadIndices) {
            const up = gp.buttons[12]?.pressed;
            const down = gp.buttons[13]?.pressed;
            const left = gp.buttons[14]?.pressed;
            const right = gp.buttons[15]?.pressed;
            if (up && right) return 'upright';
            if (up && left) return 'upleft';
            if (down && right) return 'downright';
            if (down && left) return 'downleft';
            if (up) return 'up';
            if (down) return 'down';
            if (left) return 'left';
            if (right) return 'right';
        }
        return 'idle';
    }

    function pollGamepad() {
        const gamepads = navigator.getGamepads();
        const gp = currentGamepadIndex !== null ? gamepads[currentGamepadIndex] : null;

        if (!gp) {
            scheduleNextPoll();
            return;
        }

        const usesDpadButtons = gp.axes[9] === undefined && gp.buttons.length > 15;
        const direction = getDirection(gp, usesDpadButtons);

        if (directionsShareCharge(heldDirection, direction)) {
            heldDirectionFrames++;
            if (heldDirection !== direction) heldDirection = direction;
        } else {
            heldDirection = direction;
            heldDirectionFrames = 1;
        }

        const buttonIndices = [];
        for (let i = 0; i < gp.buttons.length; i++) {
            if (usesDpadButtons && i >= 12 && i <= 15) continue;
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
        const usesDpadButtons = gp.axes[9] === undefined && gp.buttons.length > 15;

        directionRowsEl.innerHTML = '';
        DIRECTIONS.forEach((dir) => {
            directionRowsEl.appendChild(buildMappingRow({
                label: DIRECTION_LABELS[dir],
                previewSrc: profile.directions[dir],
                onUpload: async (file) => {
                    const dataUrl = await resizeImageFile(file);
                    profile.directions[dir] = dataUrl;
                    saveStore(store);
                    return dataUrl;
                },
                onClear: () => {
                    delete profile.directions[dir];
                    saveStore(store);
                },
            }));
        });

        buttonRowsEl.innerHTML = '';
        for (let i = 0; i < gp.buttons.length; i++) {
            if (usesDpadButtons && i >= 12 && i <= 15) continue;
            buttonRowsEl.appendChild(buildMappingRow({
                label: `Button ${i}`,
                previewSrc: profile.buttons[i],
                onUpload: async (file) => {
                    const dataUrl = await resizeImageFile(file);
                    profile.buttons[i] = dataUrl;
                    saveStore(store);
                    return dataUrl;
                },
                onClear: () => {
                    delete profile.buttons[i];
                    saveStore(store);
                },
            }));
        }
    }

    function buildMappingRow({ label, previewSrc, onUpload, onClear }) {
        const row = document.createElement('div');
        row.className = 'mapping-row';

        const preview = document.createElement('div');
        preview.className = 'mapping-preview';
        if (previewSrc) {
            const img = document.createElement('img');
            img.src = previewSrc;
            preview.appendChild(img);
        }
        row.appendChild(preview);

        const labelEl = document.createElement('span');
        labelEl.className = 'mapping-label';
        labelEl.textContent = label;
        row.appendChild(labelEl);

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.className = 'mapping-file form-control form-control-sm';
        fileInput.addEventListener('change', async () => {
            const file = fileInput.files && fileInput.files[0];
            if (!file) return;
            const dataUrl = await onUpload(file);
            preview.innerHTML = '';
            const img = document.createElement('img');
            img.src = dataUrl;
            preview.appendChild(img);
            fileInput.value = '';
        });
        row.appendChild(fileInput);

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn btn-sm btn-outline-light mapping-clear';
        clearBtn.textContent = 'Clear';
        clearBtn.addEventListener('click', () => {
            onClear();
            preview.innerHTML = '';
        });
        row.appendChild(clearBtn);

        return row;
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
