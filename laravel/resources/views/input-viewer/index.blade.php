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
                font-size: 13px;
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
                box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.7);
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

            .frame-counter {
                width: 44px;
                color: yellow;
                font-family: 'Arial Black', Impact, sans-serif;
                font-size: 20px;
                text-align: right;
                line-height: 32px;
                -webkit-text-stroke: 1px black;
                text-shadow: -1px -1px 0 black, 1px -1px 0 black, -1px 1px 0 black, 1px 1px 0 black;
            }

            .glow {
                filter: drop-shadow(0 0 6px yellow) brightness(1.2);
            }

            #watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 16px;
                opacity: 0.14;
                z-index: 0;
                transition: opacity 0.4s ease;
                pointer-events: none;
            }

            #watermark img {
                width: min(38vw, 420px);
                height: auto;
            }

            #watermark-text {
                font-size: 24px;
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

            #config-panel {
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                width: 340px;
                max-width: 90vw;
                overflow-y: auto;
                padding: 16px;
                background: rgba(0, 0, 0, 0.75);
                border-left: 1px solid rgba(255, 255, 255, 0.15);
                z-index: 2;
                transition: opacity 0.4s ease;
            }

            #config-panel.faded,
            #watermark.faded {
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
                width: 56px;
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
                padding: 10px 6px;
                font-size: 13px;
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
                font-size: 12px;
                color: rgba(255, 255, 255, 0.6);
                margin-bottom: 16px;
            }

            .mapping-section-title {
                font-size: 13px;
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
                width: 32px;
                height: 32px;
                flex: 0 0 32px;
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
                font-size: 12px;
            }

            .mapping-actions {
                display: flex;
                gap: 6px;
                flex: 0 0 auto;
            }

            .mapping-combine,
            .mapping-clear {
                flex: 0 0 auto;
                font-size: 11px;
                padding: 2px 6px;
            }

            .mapping-file {
                width: 100%;
                font-size: 11px;
            }

            .macro-picker {
                margin-top: 8px;
                padding: 8px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid rgba(255, 255, 255, 0.15);
                border-radius: 4px;
                font-size: 12px;
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
                width: 20px;
                height: 20px;
                object-fit: contain;
                border-radius: 3px;
                background: rgba(0, 0, 0, 0.3);
            }

            .macro-picker-actions {
                display: flex;
                gap: 6px;
            }

            .settings-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                margin-bottom: 8px;
                font-size: 13px;
            }

            .settings-row input {
                width: 70px;
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
                padding: 4px 10px 8px;
                font-size: 13px;
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
                font-size: 12px;
                color: rgba(255, 255, 255, 0.55);
                margin: 6px 0 0;
            }
        </style>
    </x-slot:styles>

    <div id="input-viewer">
        <div id="history"></div>

        <div id="watermark">
            <img src="/img/combosuki.webp" alt="">
            <div id="watermark-text">Provided by combo好き</div>
        </div>

        <div id="panel-toggle-zone">
            <button type="button" id="panel-toggle" aria-label="Show or hide the settings panel">Settings</button>
        </div>

        <div id="config-panel">
            <a id="back-link" href="{{ url('/') }}">&larr; combosuki</a>

            <h5 class="mb-3">Input Viewer</h5>

            <p class="panel-hint">
                Hold any button on your controller for a couple seconds to hide this panel (and the watermark) while capturing your stream.
                It stays hidden — move your mouse to the right edge of the screen to bring back the <strong>Settings</strong> tab, then click it to show this panel again.
            </p>

            <div class="mb-3">
                <label for="gamepad-selector" class="form-label mb-1" style="font-size: 13px;">Controller</label>
                <select id="gamepad-selector" class="form-select form-select-sm">
                    <option value="">Select Controller</option>
                </select>
            </div>

            <ul class="nav nav-tabs nav-tabs-combosuki mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-mappings" type="button" role="tab">Mappings</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-input" type="button" role="tab">Input</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-mappings" role="tabpanel">
                    <div class="mapping-section-title">Directions</div>
                    <div id="direction-mapping-rows"></div>

                    <div class="mapping-section-title">Buttons</div>
                    <div id="button-mapping-rows"></div>

                    <button type="button" id="reset-mappings" class="btn btn-sm btn-outline-danger mt-3">Reset mappings for this controller</button>
                </div>

                <div class="tab-pane fade" id="tab-input" role="tabpanel">
                    <div class="mb-3">
                        <label for="direction-source" class="form-label mb-1" style="font-size: 13px;">Read directions from</label>
                        <select id="direction-source" class="form-select form-select-sm">
                            <option value="dpad">D-Pad / Hat Switch (default)</option>
                            <option value="leftStick">Left Analog Stick</option>
                            <option value="rightStick">Right Analog Stick</option>
                        </select>
                        <p class="input-tab-hint">
                            Playing on a pad and want motion inputs read off the analog stick instead of the d-pad? Pick a stick here — it maps to the same 8 directions above.
                        </p>
                    </div>

                    <div class="settings-row">
                        <label for="setting-deadzone">Stick deadzone</label>
                        <input type="number" id="setting-deadzone" class="form-control form-control-sm" min="0.05" max="0.95" step="0.05">
                    </div>

                    <div class="settings-row">
                        <label for="setting-fps">Polling FPS</label>
                        <input type="number" id="setting-fps" class="form-control form-control-sm" min="1" max="240">
                    </div>
                    <div class="settings-row">
                        <label for="setting-charge">Charge frames</label>
                        <input type="number" id="setting-charge" class="form-control form-control-sm" min="1" max="999">
                    </div>
                    <div class="settings-row">
                        <label for="setting-hide">Hide after (frames)</label>
                        <input type="number" id="setting-hide" class="form-control form-control-sm" min="1" max="9999">
                    </div>
                    <div class="settings-row">
                        <label for="setting-history-limit">History length</label>
                        <input type="number" id="setting-history-limit" class="form-control form-control-sm" min="1" max="200">
                    </div>
                </div>
            </div>
        </div>
    </div>

    @vite(['resources/js/input-viewer.js'])
</x-layouts.app>
