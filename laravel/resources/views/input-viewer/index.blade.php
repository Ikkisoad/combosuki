<x-layouts.app
    :title="'Input Viewer - Combo好き'"
    description="A configurable, client-side controller input overlay for your stream. Upload your own button images, map them to your controller, and use the page as an OBS Browser Source."
>
    <x-slot:styles>
        {{--
            This page is meant to be pointed at directly as an OBS/streaming
            Browser Source, so it opts out of the site's usual red
            background/textures (set on `body` by app.css) in favor of a
            transparent one — same reasoning as the reference overlay file
            this feature is based on (`body { background: transparent; }`).
        --}}
        <style>
            {{--
                Bumps every rem-based Bootstrap size on this page (buttons,
                form-controls/selects, headings, nav-tabs, checkboxes) at
                once, on top of the px bumps below on our own custom classes
                — OBS's Interact window often renders the browser source
                shrunk to fit a small popup, so text needs to be genuinely
                large in absolute terms to still read at that scale, not
                just "large for a normal desktop page".
            --}}
            html {
                font-size: 24px;
            }

            body {
                background: transparent !important;
                background-image: none !important;
            }

            #input-viewer {
                position: relative;
                min-height: 100vh;
            }

            #back-link {
                display: inline-block;
                margin-bottom: 12px;
                font-size: 24px;
                color: rgba(255, 255, 255, 0.7);
            }

            #history {
                position: fixed;
                bottom: 16px;
                left: 16px;
                display: flex;
                flex-direction: column-reverse;
                gap: 4px;
                z-index: 1;
            }

            .input-entry {
                display: flex;
                gap: 4px;
                align-items: center;
            }

            {{-- Child combinator, not a descendant selector: a macro icon's
                 own <img> children (see .macro-icon below) are nested one
                 level deeper and must keep their own, smaller size. --}}
            .input-entry > img {
                width: 32px;
                height: 32px;
                object-fit: contain;
            }

            {{--
                A macro slot (e.g. an A+B macro button) is rendered as its
                source images fanned diagonally in front of one another, each
                offset by MACRO_STEP — see buildMacroIcon() in
                input-viewer.js. The wrapper's own size is set inline per
                image count so it still lays out like a single icon.
            --}}
            .macro-icon {
                position: relative;
                flex: 0 0 auto;
            }

            {{-- width/height are set inline per image (input-viewer.js macroImageSize) — they shrink as more images join the macro. --}}
            .macro-icon img {
                position: absolute;
                object-fit: contain;
                border-radius: 3px;
            }

            .input-chip {
                min-width: 32px;
                height: 32px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0 6px;
                font-size: 11px;
                font-family: monospace;
                background: rgba(0, 0, 0, 0.55);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 4px;
                color: white;
            }

            {{--
                Styled as a combosuki badge (same #920000/#FA591C pairing as
                .btn-combosuki in app.css) rather than a generic yellow HUD
                digit, so it reads as part of the site rather than a
                copy-pasted overlay widget. Font/color/background are CSS
                custom properties so the Input tab's settings (input-viewer.js
                applyCounterAppearance()) can override them per-viewer and
                have every counter — including ones already in the history
                feed — update live; they default to the site's own look.
            --}}
            .frame-counter {
                min-width: 40px;
                height: 32px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0 8px;
                color: var(--counter-color, white);
                font-family: var(--counter-font, inherit);
                font-weight: 700;
                font-size: 15px;
                background: var(--counter-bg, rgba(146, 0, 0, 0.7));
                border: 1px solid var(--counter-border, #FA591C);
                border-radius: 4px;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.7);
            }

            .glow {
                filter: drop-shadow(0 0 6px #FA591C) brightness(1.2);
            }

            #watermark-group {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 16px;
                z-index: 0;
                pointer-events: none;
            }

            {{--
                opacity here is its own stacking context — a child's opacity
                can never read as more visible than this, no matter how
                opaque the child itself is. #watermark-hint deliberately
                lives outside it (as a sibling, not nested inside) so its
                text can stay legible instead of being capped to 14% too.
            --}}
            #watermark {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 16px;
                opacity: 0.14;
                transition: opacity 0.4s ease;
            }

            #watermark img {
                width: min(44vw, 520px);
                height: auto;
            }

            #watermark-text {
                font-size: 34px;
                font-weight: 600;
                letter-spacing: 0.05em;
                color: white;
                text-shadow:
                    0 0 6px rgba(0, 0, 0, 0.9),
                    0 0 14px rgba(0, 0, 0, 0.7),
                    1px 1px 2px rgba(0, 0, 0, 0.95),
                    -1px -1px 2px rgba(0, 0, 0, 0.95),
                    1px -1px 2px rgba(0, 0, 0, 0.95),
                    -1px 1px 2px rgba(0, 0, 0, 0.95);
            }

            {{--
                Read while the panel is still visible (it fades away with
                everything else once the panel hides), so the viewer knows in
                advance how to bring the settings back later.
            --}}
            #watermark-hint {
                margin-top: -8px;
                max-width: 460px;
                font-size: 20px;
                font-weight: 600;
                text-align: center;
                color: white;
                opacity: 0.85;
                transition: opacity 0.4s ease;
                text-shadow:
                    0 0 6px rgba(0, 0, 0, 0.95),
                    1px 1px 2px rgba(0, 0, 0, 1),
                    -1px -1px 2px rgba(0, 0, 0, 1),
                    1px -1px 2px rgba(0, 0, 0, 1),
                    -1px 1px 2px rgba(0, 0, 0, 1);
            }

            #config-panel {
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                width: 560px;
                max-width: 90vw;
                font-size: 22px;
                overflow-y: auto;
                {{-- Extra right padding keeps row content (e.g. each mapping row's Clear button) from rendering under #panel-toggle-zone, which sits on top of the panel at a higher z-index. --}}
                padding: 16px 104px 16px 16px;
                background: rgba(0, 0, 0, 0.75);
                border-left: 1px solid rgba(255, 255, 255, 0.15);
                z-index: 2;
                transition: opacity 0.4s ease;
            }

            #config-panel.faded,
            #watermark.faded,
            #watermark-hint.faded {
                opacity: 0;
                pointer-events: none;
            }

            {{--
                A wide invisible strip along the right edge, not just the tab
                itself — once the tab is hidden it's not a viable hover
                target, so hovering anywhere near the edge is what reveals it.
            --}}
            #panel-toggle-zone {
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                width: 84px;
                z-index: 3;
                display: flex;
                align-items: center;
                justify-content: flex-end;
            }

            #panel-toggle {
                background: rgba(0, 0, 0, 0.6);
                color: white;
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-right: none;
                border-radius: 6px 0 0 6px;
                padding: 28px 16px;
                font-size: 24px;
                font-weight: 600;
                cursor: pointer;
                writing-mode: vertical-rl;
                opacity: 1;
                transition: opacity 0.3s ease;
            }

            #panel-toggle:hover {
                background: rgba(0, 0, 0, 0.85);
            }

            #panel-toggle.tab-hidden {
                opacity: 0;
            }

            #panel-toggle-zone:hover #panel-toggle.tab-hidden {
                opacity: 1;
            }

            .panel-hint {
                font-size: 22px;
                color: rgba(255, 255, 255, 0.6);
                margin-bottom: 16px;
            }

            .mapping-section-title {
                font-size: 24px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: rgba(255, 255, 255, 0.6);
                margin: 16px 0 8px;
            }

            .mapping-row {
                margin-bottom: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            }

            .mapping-row-top {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 6px;
            }

            .mapping-preview {
                width: 56px;
                height: 56px;
                flex: 0 0 56px;
                border: 1px dashed rgba(255, 255, 255, 0.35);
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: visible;
            }

            .mapping-preview > img {
                max-width: 100%;
                max-height: 100%;
            }

            .mapping-label {
                flex: 1 1 auto;
                font-size: 24px;
            }

            .mapping-actions {
                display: flex;
                gap: 8px;
                flex: 0 0 auto;
            }

            .mapping-combine,
            .mapping-clear {
                flex: 0 0 auto;
            }

            .mapping-file {
                width: 100%;
            }

            .mapping-notation {
                width: 100%;
                margin-bottom: 6px;
            }

            .macro-picker {
                margin-top: 8px;
                padding: 14px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.15);
                border-radius: 4px;
                font-size: 20px;
            }

            .macro-picker-empty {
                color: rgba(255, 255, 255, 0.55);
                font-style: italic;
            }

            .macro-picker-title {
                margin-bottom: 6px;
                color: rgba(255, 255, 255, 0.8);
            }

            .macro-picker-list {
                max-height: 160px;
                overflow-y: auto;
                margin-bottom: 8px;
            }

            .macro-picker-option {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 2px 0;
                font-weight: normal;
                cursor: pointer;
            }

            .macro-picker-thumb {
                width: 38px;
                height: 38px;
                object-fit: contain;
                border-radius: 3px;
                background: rgba(0, 0, 0, 0.3);
            }

            .macro-picker-actions {
                display: flex;
                gap: 8px;
            }

            .settings-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                margin-bottom: 8px;
                font-size: 22px;
            }

            .settings-row input {
                width: 112px;
            }

            {{-- Bootstrap's nav-tabs are styled for a light page by default — restyle for the panel's dark background. --}}
            .nav-tabs-combosuki {
                border-bottom-color: rgba(255, 255, 255, 0.2);
            }

            .nav-tabs-combosuki .nav-link {
                color: rgba(255, 255, 255, 0.6);
                background: transparent;
                border: none;
                border-bottom: 2px solid transparent;
                padding: 10px 16px 14px;
                font-size: 24px;
            }

            .nav-tabs-combosuki .nav-link:hover {
                color: white;
                border-color: rgba(255, 255, 255, 0.3);
            }

            .nav-tabs-combosuki .nav-link.active {
                color: white;
                background: transparent;
                border-color: #FA591C;
            }

            .input-tab-hint {
                font-size: 21px;
                color: rgba(255, 255, 255, 0.55);
                margin: 6px 0 0;
            }

            {{-- Bigger checkboxes/radios throughout the panel — easier to hit through OBS's interact window. --}}
            .form-check-input {
                width: 1.8em;
                height: 1.8em;
                margin-top: 0.15em;
            }

            #quick-assign-result {
                margin-top: 10px;
            }

            #quick-assign-result:empty {
                margin-top: 0;
            }

            .quick-assign-heading {
                font-size: 21px;
                font-weight: 600;
                color: #FA591C;
                margin-bottom: 6px;
            }

            {{--
                Replaces plain <select> dropdowns throughout the panel — a
                dropdown needs to be opened, found in, then closed, which is
                fiddly through OBS's small interact window. A flat list of
                big, directly clickable rows is a single click either way.
            --}}
            .radio-list {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }

            .radio-option {
                display: flex;
                align-items: center;
                gap: 14px;
                padding: 14px 16px;
                font-size: 23px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.15);
                border-radius: 4px;
                cursor: pointer;
            }

            .radio-option:hover {
                background: rgba(255, 255, 255, 0.1);
            }

            .radio-option:has(input:checked) {
                border-color: #FA591C;
                background: rgba(146, 0, 0, 0.35);
            }

            .radio-option input {
                flex: 0 0 auto;
                margin: 0;
            }

            .radio-list-empty {
                font-size: 21px;
                color: rgba(255, 255, 255, 0.55);
                font-style: italic;
                margin: 0;
            }

            .recording-status {
                margin-left: 12px;
                font-weight: 600;
                color: #FA591C;
            }

            .recording-list {
                display: flex;
                flex-direction: column;
                gap: 6px;
                max-height: 300px;
                overflow-y: auto;
                margin: 12px 0;
            }

            {{-- .input-entry/.input-entry > img are the same classes #history uses on the overlay — reused here so a recorded entry looks exactly like it did when it was captured. --}}
            .recording-entry {
                padding: 8px 10px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.15);
                border-radius: 4px;
            }

            .trial-current {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                padding: 12px 14px;
                margin-bottom: 16px;
                background: rgba(146, 0, 0, 0.25);
                border: 1px solid rgba(250, 89, 28, 0.5);
                border-radius: 4px;
            }

            .trial-current-info {
                display: flex;
                flex-direction: column;
                gap: 4px;
                min-width: 0;
            }

            .trial-current-link {
                color: #FA591C;
                font-size: 20px;
                font-weight: 600;
                text-decoration: none;
            }

            .trial-current-link:hover {
                text-decoration: underline;
            }

            .trial-search-row {
                display: flex;
                gap: 8px;
                margin-bottom: 10px;
            }

            .trial-search-row input {
                flex: 1 1 auto;
            }

            .trial-search-row button {
                flex: 0 0 auto;
            }

            .trial-result-meta {
                font-size: 19px;
                color: rgba(255, 255, 255, 0.6);
            }

            .trial-result-notation {
                margin-top: 2px;
            }

            {{--
                A persistent widget showing the active trial's combo —
                deliberately a sibling of #config-panel/#panel-toggle-zone
                (not nested inside either) so it's never touched by
                applyFadeState()'s .faded toggling: its own opacity always
                stays 1, regardless of the settings panel's state. Its
                z-index sits below both the panel (2) and the toggle tab (3)
                so it renders behind them while either is actually visible —
                once the panel fades out for capture (opacity 0), there's
                nothing opaque left to sit behind, so it reads as fully
                visible again without needing a z-index change.
            --}}
            #trial-display {
                position: fixed;
                top: 16px;
                right: 16px;
                z-index: 1;
                max-width: min(50vw, 640px);
                padding: 10px 14px;
                background: rgba(0, 0, 0, 0.65);
                border: 1px solid rgba(255, 255, 255, 0.25);
                border-radius: 6px;
            }

            #trial-character {
                font-size: 18px;
                font-weight: 600;
                color: rgba(255, 255, 255, 0.7);
                margin-bottom: 6px;
            }

            #trial-moves {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }

            .trial-move {
                display: inline-flex;
                align-items: center;
                padding: 4px 8px;
                font-family: monospace;
                font-size: 20px;
                font-weight: 700;
                background: rgba(0, 0, 0, 0.5);
                border: 1px solid rgba(255, 255, 255, 0.3);
                border-radius: 4px;
                transition: opacity 0.2s ease;
            }

            {{-- Highlighting the "up next" move (a glow on the current
                 index) is deferred to a later update — see renderTrialDisplay()
                 in input-viewer.js. V1 only marks moves already hit
                 correctly. --}}
            .trial-move.done {
                opacity: 0.3;
                text-decoration: line-through;
            }
        </style>
    </x-slot:styles>

    <div id="input-viewer">
        <div id="history"></div>

        <div id="watermark-group">
            <div id="watermark">
                <img src="/img/combosuki.webp" alt="">
                <div id="watermark-text">Provided by combo好き</div>
            </div>
            <div id="watermark-hint">Hold any button on your controller to hide this menu. Move your mouse to the right edge of the screen to bring it back.</div>
        </div>

        <div id="panel-toggle-zone">
            <button type="button" id="panel-toggle" aria-label="Show or hide the settings panel">Settings</button>
        </div>

        {{--
            The active combo trial — a sibling of #config-panel, not a child
            of it, so it stays visible regardless of the settings panel's own
            open/closed/faded state. Hidden by default until a trial is
            loaded (see input-viewer.js renderTrialDisplay()).
        --}}
        <div
            id="trial-display"
            hidden
            data-guides-search-url="{{ route('input-viewer.guides.search') }}"
            data-guide-combos-url-template="{{ route('input-viewer.guides.combos', ['list' => '__LIST__']) }}"
            data-combo-moves-url-template="{{ route('input-viewer.combos.moves', ['combo' => '__COMBO__']) }}"
            data-combo-show-url-template="{{ route('combos.show', ['combo' => '__COMBO__']) }}"
        >
            <div id="trial-character"></div>
            <div id="trial-moves"></div>
        </div>

        <div id="config-panel">
            <a id="back-link" href="{{ url('/') }}">&larr; combosuki</a>

            <h5 class="mb-3">Input Viewer</h5>

            <p class="panel-hint">
                Hold any button on your controller for a couple seconds to hide this panel (and the watermark) while capturing your stream.
                It stays hidden — move your mouse to the right edge of the screen to bring back the <strong>Settings</strong> tab, then click it to show this panel again.
            </p>

            <div class="mb-3">
                <label class="form-label mb-1" style="font-size: 22px;">Controller</label>
                <div id="gamepad-list" class="radio-list">
                    <p class="radio-list-empty">No controllers detected yet. Press a button on your controller to wake it up.</p>
                </div>
            </div>

            <ul class="nav nav-tabs nav-tabs-combosuki mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-mappings" type="button" role="tab">Mappings</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-input" type="button" role="tab">Input</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-customize" type="button" role="tab">Customize</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-recording" type="button" role="tab">Recording</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-trials" type="button" role="tab">Trials</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-mappings" role="tabpanel">
                    <div class="mapping-section-title">Directions</div>
                    <div id="direction-mapping-rows"></div>

                    <div class="mapping-section-title">Buttons</div>
                    <div id="button-mapping-rows"></div>

                    <button type="button" id="reset-mappings" class="btn btn-outline-danger mt-3">Reset mappings for this controller</button>
                </div>

                <div class="tab-pane fade" id="tab-input" role="tabpanel">
                    <div class="mapping-section-title" style="margin-top: 0;">Quick Assign</div>
                    <p class="input-tab-hint">
                        Not sure which raw button index is which on your controller? Click Listen, then press the button (or a direction) you want to set an image for — it'll open right below.
                    </p>
                    <button type="button" id="quick-assign-listen" class="btn btn-combosuki">Listen for input…</button>
                    <div id="quick-assign-result"></div>

                    <div class="mb-3 mt-4">
                        <label class="form-label mb-1" style="font-size: 22px;">Read directions from</label>
                        <div class="radio-list">
                            <label class="radio-option" for="direction-source-dpad">
                                <input type="radio" name="direction-source" id="direction-source-dpad" value="dpad">
                                D-Pad / Hat Switch (default)
                            </label>
                            <label class="radio-option" for="direction-source-leftStick">
                                <input type="radio" name="direction-source" id="direction-source-leftStick" value="leftStick">
                                Left Analog Stick
                            </label>
                            <label class="radio-option" for="direction-source-rightStick">
                                <input type="radio" name="direction-source" id="direction-source-rightStick" value="rightStick">
                                Right Analog Stick
                            </label>
                        </div>
                        <p class="input-tab-hint">
                            Playing on a pad and want motion inputs read off the analog stick instead of the d-pad? Pick a stick here — it maps to the same 8 directions above.
                        </p>
                    </div>

                    <div class="settings-row">
                        <label for="setting-deadzone">Stick deadzone</label>
                        <input type="number" id="setting-deadzone" class="form-control" min="0.05" max="0.95" step="0.05">
                    </div>

                    <div class="settings-row">
                        <label for="setting-fps">Polling FPS</label>
                        <input type="number" id="setting-fps" class="form-control" min="1" max="240">
                    </div>
                    <div class="settings-row">
                        <label for="setting-charge">Charge frames</label>
                        <input type="number" id="setting-charge" class="form-control" min="1" max="999">
                    </div>
                    <div class="settings-row">
                        <label for="setting-hide">Hide after (frames)</label>
                        <input type="number" id="setting-hide" class="form-control" min="1" max="9999">
                    </div>
                    <div class="settings-row">
                        <label for="setting-history-limit">History length</label>
                        <input type="number" id="setting-history-limit" class="form-control" min="1" max="200">
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-customize" role="tabpanel">
                    <div class="mapping-section-title" style="margin-top: 0;">Frame Counter</div>

                    <div class="mb-3">
                        <label class="form-label mb-1" style="font-size: 22px;">Font</label>
                        <div class="radio-list">
                            <label class="radio-option" for="counter-font-inherit">
                                <input type="radio" name="counter-font" id="counter-font-inherit" value="inherit">
                                Site default
                            </label>
                            <label class="radio-option" for="counter-font-impact">
                                <input type="radio" name="counter-font" id="counter-font-impact" value="'Arial Black', Impact, sans-serif">
                                Impact (classic HUD)
                            </label>
                            <label class="radio-option" for="counter-font-monospace">
                                <input type="radio" name="counter-font" id="counter-font-monospace" value="'Courier New', monospace">
                                Monospace
                            </label>
                            <label class="radio-option" for="counter-font-serif">
                                <input type="radio" name="counter-font" id="counter-font-serif" value="Georgia, 'Times New Roman', serif">
                                Serif
                            </label>
                            <label class="radio-option" for="counter-font-trebuchet">
                                <input type="radio" name="counter-font" id="counter-font-trebuchet" value="'Trebuchet MS', sans-serif">
                                Trebuchet
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="setting-counter-color" class="form-label mb-1" style="font-size: 22px;">Text color</label>
                        <input type="color" id="setting-counter-color" class="form-control form-control-color">
                    </div>

                    <div class="mb-3">
                        <label for="setting-counter-bg-color" class="form-label mb-1" style="font-size: 22px;">Background color</label>
                        <input type="color" id="setting-counter-bg-color" class="form-control form-control-color">
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="setting-counter-transparent-bg">
                        <label class="form-check-label" for="setting-counter-transparent-bg" style="font-size: 22px;">
                            Transparent background (just the number)
                        </label>
                    </div>
                </div>

                <div class="tab-pane fade" id="tab-recording" role="tabpanel">
                    <div class="mapping-section-title" style="margin-top: 0;">Keyboard Hotkey</div>
                    <p class="input-tab-hint">
                        This hotkey only fires while this page has keyboard focus — e.g. OBS's <strong>Interact</strong> window, or a regular browser tab. It can't listen system-wide while you're alt-tabbed into your game.
                    </p>
                    <div class="settings-row">
                        <span id="recording-hotkey-current">No hotkey set.</span>
                        <button type="button" id="recording-hotkey-set" class="btn btn-outline-light">Set Hotkey</button>
                    </div>

                    <div class="mapping-section-title">Controller Hotkey</div>
                    <p class="input-tab-hint">
                        A controller button works even without keyboard focus, so it's the more reliable option once you're actually playing — select a controller above, then bind a button here. That button is then reserved for this and won't show up in Mappings.
                    </p>
                    <div class="settings-row">
                        <span id="recording-gamepad-hotkey-current">No controller button set.</span>
                        <button type="button" id="recording-gamepad-hotkey-set" class="btn btn-outline-light">Set Controller Hotkey</button>
                    </div>

                    <div class="mapping-section-title">Record</div>
                    <button type="button" id="recording-toggle" class="btn btn-combosuki">Start Recording</button>
                    <span id="recording-status" class="recording-status"></span>

                    <div class="mapping-section-title">Recorded Inputs</div>
                    <div id="recording-list" class="recording-list">
                        <p class="radio-list-empty">Nothing recorded yet.</p>
                    </div>

                    <div class="mb-3">
                        <label for="recording-notation" class="form-label mb-1" style="font-size: 22px;">Notation</label>
                        <textarea id="recording-notation" class="form-control" rows="3" readonly placeholder="Notation appears here after you stop recording."></textarea>
                    </div>
                    <button type="button" id="recording-copy" class="btn btn-outline-light">Copy to Clipboard</button>
                </div>

                <div class="tab-pane fade" id="tab-trials" role="tabpanel">
                    <div class="mapping-section-title" style="margin-top: 0;">Combo Trial</div>
                    <p class="input-tab-hint">
                        Load a combo from an existing guide to practice it — it shows up in the top-right corner and stays there even while this panel is hidden. Each move greys out once you hit it correctly in order; a wrong input or too long a pause resets your progress.
                    </p>

                    <div id="trial-current" class="trial-current" hidden>
                        <div class="trial-current-info">
                            <span id="trial-current-label"></span>
                            <a id="trial-current-link" class="trial-current-link" href="#" target="_blank" rel="noopener">View combo &rarr;</a>
                        </div>
                        <button type="button" id="trial-clear" class="btn btn-outline-danger">Clear Trial</button>
                    </div>

                    <div class="settings-row" hidden>
                        <label for="setting-trial-timeout">Reset after (seconds)</label>
                        <input type="number" id="setting-trial-timeout" class="form-control" min="1" max="30">
                    </div>

                    <div class="mapping-section-title" style="margin-top: 0;">Load by Combo ID</div>
                    <div class="trial-search-row">
                        <input type="number" id="trial-combo-id-input" class="form-control" placeholder="Combo ID…" min="1">
                        <button type="button" id="trial-load-by-id-btn" class="btn btn-combosuki">Load</button>
                    </div>
                    <p id="trial-combo-id-warning" class="text-danger" style="font-size: 20px; margin: 6px 0 0;" hidden></p>

                    <div class="mapping-section-title">Find a Guide</div>
                    <div class="mb-3">
                        <label for="trial-game-select" class="form-label mb-1" style="font-size: 22px;">Game</label>
                        <select id="trial-game-select" class="form-select">
                            <option value="">Select a game…</option>
                            @foreach ($games as $game)
                                <option value="{{ $game->idgame }}">{{ $game->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="trial-guide-select" class="form-label mb-1" style="font-size: 22px;">Guide</label>
                        <select id="trial-guide-select" class="form-select" disabled>
                            <option value="">Select a game first…</option>
                        </select>
                    </div>

                    <div id="trial-combos-section" hidden>
                        <div class="mapping-section-title">Pick a Combo</div>
                        <div class="trial-search-row">
                            <input type="text" id="trial-combo-search" class="form-control" placeholder="Filter by notation or character…">
                            <button type="button" id="trial-combo-search-btn" class="btn btn-combosuki">Filter</button>
                        </div>
                        <div id="trial-combo-results" class="radio-list"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/input-viewer.js'])
</x-layouts.app>
