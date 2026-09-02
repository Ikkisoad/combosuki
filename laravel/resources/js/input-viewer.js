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
    // 'inherit' follows the site's own font stack (Bootstrap's default) —
    // see the "Frame counter font" setting for the other choices.
    counterFont: 'inherit',
    counterColor: '#ffffff',
    counterBgColor: '#920000',
    counterTransparentBg: false,
    // KeyboardEvent.code of the start/stop-recording hotkey, e.g. "F9". Only
    // fires while this page actually has keyboard focus — see the Recording
    // tab's hint text.
    recordingHotkeyCode: null,
    // Raw gamepad button index that also toggles recording — unlike the
    // keyboard hotkey, this is read via Gamepad API polling, so it works
    // without the page needing keyboard focus. Once set, that button is
    // reserved for this and excluded from the mappable button list, the
    // same way d-pad buttons are excluded once they're read as directions.
    recordingHotkeyButtonIndex: null,
};

// Standard numpad notation, used as a direction's notation fallback until
// the user names it explicitly (see getNotationName/rawNotationName below).
const DIRECTION_NOTATION_DEFAULTS = {
    up: '8',
    upright: '9',
    right: '6',
    downright: '3',
    down: '2',
    downleft: '1',
    left: '4',
    upleft: '7',
    idle: '5',
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
    const profile = store.profiles[gamepadId];
    // Backfills fields added after some profiles were already saved to
    // localStorage — existing button/direction image data needs no
    // migration since these are separate, independent maps.
    if (!profile.buttonNames) profile.buttonNames = {};
    if (!profile.directionNames) profile.directionNames = {};
    if (!profile.lastRecording) profile.lastRecording = [];
    return profile;
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

// The notation name is independent of the slot's image — a button can have
// an icon uploaded, a name set, both, or neither. rawNotationName() is only
// what the user actually typed (used to populate an input's value);
// defaultNotationName() is the numpad-digit/B{n} fallback (used as an
// input's placeholder, and by getNotationName() when nothing was typed).
function rawNotationName(profile, kind, key) {
    const bucket = kind === 'direction' ? profile.directionNames : profile.buttonNames;
    return (bucket && bucket[key]) || '';
}

function defaultNotationName(kind, key) {
    return kind === 'direction' ? (DIRECTION_NOTATION_DEFAULTS[key] || key) : `B${key}`;
}

function getNotationName(profile, kind, key) {
    return rawNotationName(profile, kind, key) || defaultNotationName(kind, key);
}

function setNotationName(store, profile, kind, key, value) {
    const bucket = kind === 'direction' ? profile.directionNames : profile.buttonNames;
    const trimmed = value.trim();
    if (trimmed) {
        bucket[key] = trimmed;
    } else {
        delete bucket[key];
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

    function applyCounterAppearance(settings) {
        root.style.setProperty('--counter-font', settings.counterFont);
        root.style.setProperty('--counter-color', settings.counterColor);
        if (settings.counterTransparentBg) {
            root.style.setProperty('--counter-bg', 'transparent');
            root.style.setProperty('--counter-border', 'transparent');
        } else {
            root.style.setProperty('--counter-bg', settings.counterBgColor);
            root.style.removeProperty('--counter-border');
        }
    }

    const store = loadStore();

    const historyEl = document.getElementById('history');
    const watermarkEl = document.getElementById('watermark');
    const watermarkHintEl = document.getElementById('watermark-hint');
    const panelEl = document.getElementById('config-panel');
    const toggleTab = document.getElementById('panel-toggle');
    const gamepadListEl = document.getElementById('gamepad-list');
    const buttonRowsEl = document.getElementById('button-mapping-rows');
    const directionRowsEl = document.getElementById('direction-mapping-rows');
    const resetButton = document.getElementById('reset-mappings');
    const fpsInput = document.getElementById('setting-fps');
    const chargeInput = document.getElementById('setting-charge');
    const hideInput = document.getElementById('setting-hide');
    const historyLimitInput = document.getElementById('setting-history-limit');
    const deadzoneInput = document.getElementById('setting-deadzone');
    const quickAssignButton = document.getElementById('quick-assign-listen');
    const quickAssignResult = document.getElementById('quick-assign-result');
    const counterColorInput = document.getElementById('setting-counter-color');
    const counterBgColorInput = document.getElementById('setting-counter-bg-color');
    const counterTransparentBgInput = document.getElementById('setting-counter-transparent-bg');
    const hotkeySetButton = document.getElementById('recording-hotkey-set');
    const hotkeyCurrentEl = document.getElementById('recording-hotkey-current');
    const gamepadHotkeySetButton = document.getElementById('recording-gamepad-hotkey-set');
    const gamepadHotkeyCurrentEl = document.getElementById('recording-gamepad-hotkey-current');
    const recordButton = document.getElementById('recording-toggle');
    const recordingStatusEl = document.getElementById('recording-status');
    const recordingListEl = document.getElementById('recording-list');
    const notationOutputEl = document.getElementById('recording-notation');
    const copyNotationButton = document.getElementById('recording-copy');

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

    // "Tap to set": while true, the next newly-pressed button (or newly-hit
    // direction) on the selected pad is captured and used to open a mapping
    // row for it — lets a user identify a raw button index by physically
    // pressing it instead of guessing from a list.
    let listeningForInput = false;
    let previouslyPressedButtons = new Set();
    let previousPolledDirection = 'idle';

    let isRecording = false;
    let recordingBuffer = [];
    let listeningForHotkey = false;
    let listeningForGamepadHotkey = false;
    let previousRawPressedButtons = new Set();

    function currentProfile() {
        return currentGamepadId ? getProfile(store, currentGamepadId) : null;
    }

    function pollingInterval() {
        return 1000 / Math.max(1, store.settings.pollingFPS);
    }

    function applyFadeState() {
        panelEl.classList.toggle('faded', panelHidden);
        watermarkEl.classList.toggle('faded', panelHidden);
        watermarkHintEl.classList.toggle('faded', panelHidden);
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

    // A button index that's "spoken for" by something other than combo
    // input — currently just the gamepad recording hotkey (see
    // recordingHotkeyButtonIndex) — is excluded the same way d-pad buttons
    // are: it never shows up in history/recording, and isn't offered as a
    // mappable button either.
    function isReservedButtonIndex(i, excludeDpadButtons) {
        if (excludeDpadButtons && i >= 12 && i <= 15) return true;
        return store.settings.recordingHotkeyButtonIndex === i;
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
            if (isReservedButtonIndex(i, excludeDpadButtons)) continue;
            if (gp.buttons[i]?.pressed) buttonIndices.push(i);
        }

        if (listeningForInput) {
            const newlyPressed = buttonIndices.find((i) => !previouslyPressedButtons.has(i));
            if (newlyPressed !== undefined) {
                handleListenedInput('button', String(newlyPressed), `Button ${newlyPressed}`);
            } else if (direction !== 'idle' && previousPolledDirection === 'idle') {
                handleListenedInput('direction', direction, DIRECTION_LABELS[direction] || direction);
            }
        }
        previouslyPressedButtons = new Set(buttonIndices);
        previousPolledDirection = direction;

        // Gamepad recording hotkey — tracked independently of buttonIndices
        // above (which already excludes this index) via the raw pressed
        // state, so both listening-to-set-it and triggering-it work even
        // while the button is excluded from everything else.
        const rawPressed = new Set();
        for (let i = 0; i < gp.buttons.length; i++) {
            if (gp.buttons[i]?.pressed) rawPressed.add(i);
        }
        const newlyPressedRaw = [...rawPressed].filter((i) => !previousRawPressedButtons.has(i));

        if (listeningForGamepadHotkey) {
            if (newlyPressedRaw.length > 0) {
                store.settings.recordingHotkeyButtonIndex = newlyPressedRaw[0];
                saveStore(store);
                renderGamepadHotkeyDisplay();
                setGamepadHotkeyListening(false);
                renderMappingRows(gp);
            }
        } else if (store.settings.recordingHotkeyButtonIndex !== null && newlyPressedRaw.includes(store.settings.recordingHotkeyButtonIndex)) {
            toggleRecording();
        }
        previousRawPressedButtons = rawPressed;

        const signature = JSON.stringify({ direction, buttonIndices: [...buttonIndices].sort((a, b) => a - b) });

        const isMeaningfulInput = direction !== 'idle' || buttonIndices.length > 0;

        if (signature === lastSignature && lastEntry) {
            frameCount++;
            const counter = lastEntry.querySelector('.frame-counter');
            if (counter) counter.textContent = frameCount;

            const dirIcon = lastEntry.children[1];
            if (dirIcon && direction !== 'idle') {
                dirIcon.classList.toggle('glow', heldDirectionFrames >= store.settings.chargeThreshold);
            }

            if (isRecording && isMeaningfulInput && recordingBuffer.length > 0) {
                recordingBuffer[recordingBuffer.length - 1].frames++;
            }
        } else {
            frameCount = 1;
            lastSignature = signature;
            lastEntry = addInputToHistory(direction, buttonIndices);

            if (isRecording && isMeaningfulInput) {
                recordingBuffer.push({ direction, buttons: [...buttonIndices], frames: 1 });
            }
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
        gamepadListEl.innerHTML = '';
        let matchedIndex = null;
        let anyConnected = false;

        for (let i = 0; i < gamepads.length; i++) {
            const gp = gamepads[i];
            if (!gp) continue;
            anyConnected = true;

            const optionId = `gamepad-radio-${i}`;
            const option = document.createElement('label');
            option.className = 'radio-option';
            option.htmlFor = optionId;

            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'gamepad-choice';
            radio.id = optionId;
            radio.value = String(i);
            if (store.lastGamepadId && gp.id === store.lastGamepadId) {
                radio.checked = true;
                matchedIndex = i;
            }
            radio.addEventListener('change', () => {
                const gamepadsNow = navigator.getGamepads();
                if (gamepadsNow[i]) selectGamepad(i, gamepadsNow[i]);
            });
            option.appendChild(radio);

            const text = document.createElement('span');
            text.textContent = `${i}: ${gp.id}`;
            option.appendChild(text);

            gamepadListEl.appendChild(option);
        }

        if (!anyConnected) {
            const empty = document.createElement('p');
            empty.className = 'radio-list-empty';
            empty.textContent = 'No controllers detected yet. Press a button on your controller to wake it up.';
            gamepadListEl.appendChild(empty);
        }

        // Guard against re-selecting (and so resetting Quick Assign/re-
        // rendering everything) on every periodic rescan when the already-
        // selected pad is still the one that matched.
        if (matchedIndex !== null && (currentGamepadIndex !== matchedIndex || currentGamepadId !== gamepads[matchedIndex].id)) {
            selectGamepad(matchedIndex, gamepads[matchedIndex]);
        }
    }

    function selectGamepad(index, gp) {
        currentGamepadIndex = index;
        currentGamepadId = gp.id;
        store.lastGamepadId = gp.id;
        saveStore(store);
        setListening(false);
        setGamepadHotkeyListening(false);
        quickAssignResult.innerHTML = '';

        if (isRecording) {
            isRecording = false;
            setRecordingUi(false);
        }

        renderMappingRows(gp);
        renderRecording(getProfile(store, gp.id));
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
            if (isReservedButtonIndex(i, excludeDpadButtons)) continue;
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

        // renderMappingRows(gp) below rebuilds the Mappings-tab list from
        // scratch, but this exact row instance can also be mounted
        // standalone (see handleListenedInput's Quick Assign result), which
        // that rebuild never touches — refresh this row's own preview too so
        // both places stay correct regardless of where the row lives.
        function refreshLocalPreview() {
            renderPreview(preview, getSlotValue(profile, kind, key), label, profile);
        }

        const labelEl = document.createElement('span');
        labelEl.className = 'mapping-label';
        labelEl.textContent = label;
        topLine.appendChild(labelEl);

        const actions = document.createElement('div');
        actions.className = 'mapping-actions';

        const combineBtn = document.createElement('button');
        combineBtn.type = 'button';
        combineBtn.className = 'btn btn-outline-light mapping-combine';
        combineBtn.textContent = 'Combine…';
        actions.appendChild(combineBtn);

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn btn-outline-light mapping-clear';
        clearBtn.textContent = 'Clear';
        clearBtn.addEventListener('click', () => {
            setSlotValue(store, profile, kind, key, undefined);
            refreshLocalPreview();
            renderMappingRows(gp);
        });
        actions.appendChild(clearBtn);

        topLine.appendChild(actions);
        row.appendChild(topLine);

        // The notation name recording uses to write this slot into combo
        // text (see buildNotationString) — independent of the image above.
        const notationInput = document.createElement('input');
        notationInput.type = 'text';
        notationInput.className = 'mapping-notation form-control';
        notationInput.placeholder = `Notation (defaults to "${defaultNotationName(kind, key)}")`;
        notationInput.value = rawNotationName(profile, kind, key);
        notationInput.addEventListener('change', () => {
            setNotationName(store, profile, kind, key, notationInput.value);
        });
        row.appendChild(notationInput);

        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.className = 'mapping-file form-control';
        fileInput.addEventListener('change', async () => {
            const file = fileInput.files && fileInput.files[0];
            if (!file) return;
            const dataUrl = await resizeImageFile(file);
            setSlotValue(store, profile, kind, key, dataUrl);
            refreshLocalPreview();
            renderMappingRows(gp);
        });
        row.appendChild(fileInput);

        const picker = buildMacroPicker({ kind, key, label, profile, gp, onSaved: refreshLocalPreview });
        row.appendChild(picker);

        combineBtn.addEventListener('click', () => {
            picker.hidden = !picker.hidden;
        });

        return row;
    }

    // Lets a slot (e.g. a macro button like A+B on a fight stick) be set to
    // several already-uploaded images at once instead of a single upload —
    // see buildMacroIcon for how that's rendered as a compact diagonal fan.
    function buildMacroPicker({ kind, key, label, profile, gp, onSaved }) {
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
        saveBtn.className = 'btn btn-combosuki';
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
            onSaved?.();
            renderMappingRows(gp);
        });
        actions.appendChild(saveBtn);

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-outline-light';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.addEventListener('click', () => {
            wrap.hidden = true;
        });
        actions.appendChild(cancelBtn);

        wrap.appendChild(actions);

        return wrap;
    }

    function setListening(next) {
        listeningForInput = next;
        quickAssignButton.textContent = next ? 'Waiting for input…' : 'Listen for input…';
        quickAssignButton.classList.toggle('btn-outline-light', next);
        quickAssignButton.classList.toggle('btn-combosuki', !next);
    }

    // Called from pollGamepad once a press is detected while listening —
    // opens the same row buildMappingRow() renders in the Mappings tab, but
    // inline here, so the just-identified button/direction can be assigned
    // an image immediately without hunting for it in a long list.
    function handleListenedInput(kind, key, label) {
        setListening(false);

        const gamepads = navigator.getGamepads();
        const gp = currentGamepadIndex !== null ? gamepads[currentGamepadIndex] : null;
        const profile = currentProfile();
        if (!gp || !profile) return;

        quickAssignResult.innerHTML = '';

        const heading = document.createElement('div');
        heading.className = 'quick-assign-heading';
        heading.textContent = `Detected: ${label}`;
        quickAssignResult.appendChild(heading);

        quickAssignResult.appendChild(buildMappingRow({ kind, key, label, profile, gp }));
    }

    quickAssignButton.addEventListener('click', () => {
        if (!currentGamepadId) {
            alert('Select a controller first.');
            return;
        }
        if (listeningForInput) {
            setListening(false);
            return;
        }
        quickAssignResult.innerHTML = '';
        setListening(true);
    });

    // A direction held while a button is tapped a few times produces one
    // bare (button-less) entry per press/release (holding 2 while tapping
    // three buttons records 2, 2B0, 2, 2B3, 2, 2B5, 2), and a motion like
    // 236 leading into a button produces one bare entry per direction step
    // (2, 3, 6, 6B0). Neither is a distinct "beat" on its own — a bare
    // entry only means something once it lands on a button (or the
    // recording ends mid-motion). This walks the raw buffer collecting
    // direction changes into a pending motion buffer and flushes that
    // buffer — concatenated, e.g. "236" — into whatever comes next: a
    // button press (producing "236B0"), or nothing (a trailing motion with
    // no button after it, kept on its own). A button entry's own direction
    // always joins the buffer too, deduped only against the immediately
    // preceding step, so "2B0, 2B3, 2B5" each still show their own "2"
    // (repetition across separate beats is meaningful) while "2, 3, 6, 6B0"
    // collapses the redundant lead-in "2"/"6" into a single "236B0".
    function collapseRecordingEntries(rawEntries) {
        const result = [];
        let pendingDirections = [];
        let previousButtons = [];

        rawEntries.forEach((entry) => {
            // The direction stick and a held button rarely release on the
            // exact same poll tick — one lags the other by a frame or two,
            // producing a spurious extra entry with the same buttons (or a
            // subset of them) under a now-decayed direction, e.g. a real
            // "236B0" followed by a trailing "idle,[0]" as the stick
            // relaxes while B0 is still held. Nothing NEW was pressed, so
            // it's not a new beat — drop it, but still track it so a
            // further wobble on the way down keeps getting dropped too.
            if (entry.buttons.length > 0 && previousButtons.length > 0 && entry.buttons.every((b) => previousButtons.includes(b))) {
                previousButtons = entry.buttons;
                return;
            }
            previousButtons = entry.buttons;

            // Bare (button-less) entries are always a real motion step —
            // pollGamepad never records one at idle with nothing pressed —
            // so just accumulate it, deduped against the last step.
            if (entry.buttons.length === 0) {
                if (pendingDirections[pendingDirections.length - 1] !== entry.direction) {
                    pendingDirections.push(entry.direction);
                }
                return;
            }

            // A button entry's own direction is what actually happened at
            // the moment of the press. Landing back at neutral ("idle")
            // breaks any in-progress motion — rather than either dropping
            // it or leaking the stale lead-in through, show "5" (or
            // whatever idle is named) explicitly, since idle is only ever
            // meant to be ignored when nothing is pressed alongside it.
            let directions;
            if (entry.direction === 'idle') {
                directions = ['idle'];
            } else if (pendingDirections[pendingDirections.length - 1] === entry.direction) {
                directions = pendingDirections;
            } else {
                directions = [...pendingDirections, entry.direction];
            }

            result.push({ directions, buttons: entry.buttons, frames: entry.frames });
            pendingDirections = [];
        });

        if (pendingDirections.length > 0) {
            result.push({ directions: pendingDirections, buttons: [], frames: 1 });
        }

        return result;
    }

    // Builds the copy-to-clipboard combo string: no space between a
    // direction and its buttons (e.g. "5LP"), " > " between successive
    // inputs — matching the notation convention used elsewhere on
    // combosuki (see App\Services\ComboNotationRenderer). An input held for
    // at least the Charge frames setting gets its whole token wrapped in
    // [...], mirroring real charge-motion notation like "[4]6P".
    function buildNotationString(entries, profile) {
        return entries
            .map((entry) => {
                const dirTokens = entry.directions.map((d) => getNotationName(profile, 'direction', d)).join('');
                const buttonTokens = entry.buttons.map((b) => getNotationName(profile, 'button', String(b))).join('');
                const token = dirTokens + buttonTokens;
                return entry.frames >= store.settings.chargeThreshold ? `[${token}]` : token;
            })
            .join(' > ');
    }

    function renderRecordingList(entries, profile) {
        recordingListEl.innerHTML = '';

        if (entries.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'radio-list-empty';
            empty.textContent = 'Nothing recorded yet.';
            recordingListEl.appendChild(empty);
            return;
        }

        entries.forEach((entry) => {
            const item = document.createElement('div');
            item.className = 'input-entry recording-entry';

            entry.directions.forEach((direction, i) => {
                const dirIcon = buildIconNode(getSlotValue(profile, 'direction', direction), DIRECTION_LABELS[direction] || direction, profile);
                if (i === entry.directions.length - 1 && entry.frames >= store.settings.chargeThreshold) {
                    dirIcon.classList.add('glow');
                }
                item.appendChild(dirIcon);
            });

            entry.buttons.forEach((b) => {
                item.appendChild(buildIconNode(getSlotValue(profile, 'button', String(b)), `B${b}`, profile));
            });

            recordingListEl.appendChild(item);
        });
    }

    function renderRecording(profile) {
        renderRecordingList(profile.lastRecording, profile);
        notationOutputEl.value = buildNotationString(profile.lastRecording, profile);
    }

    function setRecordingUi(recording) {
        recordButton.textContent = recording ? 'Stop Recording' : 'Start Recording';
        recordButton.classList.toggle('btn-outline-danger', recording);
        recordButton.classList.toggle('btn-combosuki', !recording);
        recordingStatusEl.textContent = recording ? 'Recording…' : '';
    }

    function toggleRecording() {
        if (!currentGamepadId) {
            alert('Select a controller first.');
            return;
        }

        isRecording = !isRecording;
        setRecordingUi(isRecording);

        if (isRecording) {
            recordingBuffer = [];
        } else {
            const profile = currentProfile();
            profile.lastRecording = collapseRecordingEntries(recordingBuffer);
            saveStore(store);
            renderRecording(profile);
        }
    }

    recordButton.addEventListener('click', toggleRecording);

    function formatKeyCode(code) {
        if (code.startsWith('Key')) return code.slice(3);
        if (code.startsWith('Digit')) return code.slice(5);
        return code;
    }

    function renderHotkeyDisplay() {
        hotkeyCurrentEl.textContent = store.settings.recordingHotkeyCode
            ? `Current hotkey: ${formatKeyCode(store.settings.recordingHotkeyCode)}`
            : 'No hotkey set.';
    }

    function setHotkeyListening(next) {
        listeningForHotkey = next;
        hotkeySetButton.textContent = next ? 'Press a key…' : 'Set Hotkey';
        hotkeySetButton.classList.toggle('btn-outline-light', !next);
        hotkeySetButton.classList.toggle('btn-combosuki', next);
    }

    hotkeySetButton.addEventListener('click', () => {
        setHotkeyListening(!listeningForHotkey);
    });

    function renderGamepadHotkeyDisplay() {
        gamepadHotkeyCurrentEl.textContent = store.settings.recordingHotkeyButtonIndex !== null
            ? `Current button: ${store.settings.recordingHotkeyButtonIndex}`
            : 'No controller button set.';
    }

    function setGamepadHotkeyListening(next) {
        listeningForGamepadHotkey = next;
        gamepadHotkeySetButton.textContent = next ? 'Press a button…' : 'Set Controller Hotkey';
        gamepadHotkeySetButton.classList.toggle('btn-outline-light', !next);
        gamepadHotkeySetButton.classList.toggle('btn-combosuki', next);
    }

    gamepadHotkeySetButton.addEventListener('click', () => {
        if (!currentGamepadId) {
            alert('Select a controller first.');
            return;
        }
        setGamepadHotkeyListening(!listeningForGamepadHotkey);
    });

    // Global, not scoped to the panel — the whole point is toggling
    // recording without having to click back into it. Ignored while a form
    // field has focus (so typing "9" into a settings field can't trigger
    // it) except the hotkey button itself while actively listening.
    document.addEventListener('keydown', (event) => {
        if (listeningForHotkey) {
            event.preventDefault();
            store.settings.recordingHotkeyCode = event.code;
            saveStore(store);
            renderHotkeyDisplay();
            setHotkeyListening(false);
            return;
        }

        const target = event.target;
        const isFormField = target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement;
        if (isFormField) return;

        if (store.settings.recordingHotkeyCode && event.code === store.settings.recordingHotkeyCode) {
            event.preventDefault();
            toggleRecording();
        }
    });

    copyNotationButton.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(notationOutputEl.value);
        } catch {
            notationOutputEl.select();
            document.execCommand('copy');
        }
        const original = copyNotationButton.textContent;
        copyNotationButton.textContent = 'Copied!';
        setTimeout(() => {
            copyNotationButton.textContent = original;
        }, 1500);
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

    // Radio-group equivalent of wireSetting() — used in place of a <select>
    // for the same reason as the gamepad list (see refreshGamepadList): a
    // dropdown has to be opened, found in, then closed, which is fiddly
    // through OBS's small interact window.
    function wireRadioGroup(name, initialValue, onChange) {
        document.querySelectorAll(`input[name="${name}"]`).forEach((radio) => {
            radio.checked = radio.value === initialValue;
            radio.addEventListener('change', () => {
                if (radio.checked) onChange(radio.value);
            });
        });
    }

    wireSetting(fpsInput, 'pollingFPS');
    wireSetting(chargeInput, 'chargeThreshold');
    wireSetting(hideInput, 'hideThreshold');
    wireSetting(historyLimitInput, 'historyLimit');

    wireRadioGroup('direction-source', store.settings.directionSource, (value) => {
        store.settings.directionSource = value;
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

    applyCounterAppearance(store.settings);
    renderHotkeyDisplay();
    renderGamepadHotkeyDisplay();

    wireRadioGroup('counter-font', store.settings.counterFont, (value) => {
        store.settings.counterFont = value;
        applyCounterAppearance(store.settings);
        saveStore(store);
    });

    counterColorInput.value = store.settings.counterColor;
    counterColorInput.addEventListener('input', () => {
        store.settings.counterColor = counterColorInput.value;
        applyCounterAppearance(store.settings);
        saveStore(store);
    });

    counterBgColorInput.value = store.settings.counterBgColor;
    counterBgColorInput.addEventListener('input', () => {
        store.settings.counterBgColor = counterBgColorInput.value;
        applyCounterAppearance(store.settings);
        saveStore(store);
    });

    counterTransparentBgInput.checked = store.settings.counterTransparentBg;
    counterTransparentBgInput.addEventListener('change', () => {
        store.settings.counterTransparentBg = counterTransparentBgInput.checked;
        applyCounterAppearance(store.settings);
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

    // A gamepad that was already connected before this page loaded doesn't
    // always fire 'gamepadconnected' (that event is for connects that
    // happen *while* the page is open) and getGamepads() can take a moment
    // to report it too — a short burst of rescans right after load catches
    // those without requiring the user to press a button first.
    let startupScans = 0;
    const startupScanTimer = setInterval(() => {
        refreshGamepadList();
        startupScans++;
        if (startupScans >= 6) clearInterval(startupScanTimer);
    }, 500);
}

document.addEventListener('DOMContentLoaded', initInputViewer);
