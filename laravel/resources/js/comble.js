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

// Combo notation spacing (e.g. the " > " between chained moves) is a
// display convention, not something a player should have to reproduce
// blindly — see CombleGuessEvaluator::starterResult(), which strips spaces
// from both the guess and the target before comparing. Blocking spaces at
// the input keeps the 6-character guess limited to actual notation and
// matches what the evaluator will end up comparing anyway.
function initStarterInput() {
    const starterInput = document.getElementById('comble-starter');

    if (! starterInput) {
        return;
    }

    starterInput.addEventListener('keydown', function (event) {
        if (event.key === ' ') {
            event.preventDefault();
        }
    });

    starterInput.addEventListener('input', function () {
        if (starterInput.value.includes(' ')) {
            starterInput.value = starterInput.value.replace(/\s+/g, '');
        }
    });
}

function initGuessForm() {
    const catalogEl = document.getElementById('comble-catalog');
    const gameSelect = document.getElementById('comble-game');
    const characterSelect = document.getElementById('comble-character');
    const typeSelect = document.getElementById('comble-type');

    initStarterInput();

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

// Set once bootDiscordActivity() completes the Discord handshake — null for
// every normal (cookie-based) visit, which is the vast majority of
// requests this page ever serves. submitGuessForm() attaches it as a
// Bearer token when present; the guess form's own action attribute (baked
// into whichever fragment — comble/_game or activity/_comble-game — is
// currently in the DOM) already points at the right endpoint either way.
let activityToken = null;

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

    const headers = { Accept: 'application/json' };

    if (activityToken) {
        headers.Authorization = `Bearer ${activityToken}`;
    }

    fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers,
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

/**
 * Discord Activity bootstrap — only ever runs when this page is loaded
 * inside an iframe. SecurityHeaders' CSP only allows Discord's own client
 * to frame this page at all (every other origin is refused), so being
 * framed is by itself a reliable signal that this is an actual Discord
 * Activity launch, not a normal visit — no undocumented Discord query
 * parameters to rely on.
 *
 * The page has already rendered and works completely fine standalone by
 * the time this runs (initGuessForm() below already drives it against the
 * cookie-based comble.guess endpoint) — this only *replaces* the game
 * widget with the Discord-identified one once the handshake completes.
 * Any failure here (no Discord parent, handshake error, timeout) just
 * leaves the already-working cookie-based page exactly as it was.
 *
 * @discord/embedded-app-sdk is dynamically imported so its ~140KB bundle
 * is never downloaded by the vast majority of visits that aren't inside
 * Discord at all.
 */
async function bootDiscordActivity() {
    const applicationId = document.querySelector('meta[name="discord-application-id"]')?.content;
    const urlsEl = document.getElementById('activity-comble-urls');

    if (! applicationId || ! urlsEl) {
        return;
    }

    const urls = JSON.parse(urlsEl.textContent);

    try {
        const { DiscordSDK } = await import('@discord/embedded-app-sdk');
        const discordSdk = new DiscordSDK(applicationId);
        await discordSdk.ready();

        const { code } = await discordSdk.commands.authorize({
            client_id: applicationId,
            response_type: 'code',
            state: '',
            prompt: 'none',
            scope: ['identify'],
        });

        const tokenResponse = await fetch(urls.token, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ code }),
        });

        if (! tokenResponse.ok) {
            throw new Error('Activity token exchange failed');
        }

        const tokenData = await tokenResponse.json();
        activityToken = tokenData.token;

        await discordSdk.commands.authenticate({ access_token: tokenData.access_token });

        const stateResponse = await fetch(urls.state, {
            headers: { Accept: 'application/json', Authorization: `Bearer ${activityToken}` },
        });

        if (! stateResponse.ok) {
            activityToken = null;
            return;
        }

        const data = await stateResponse.json();
        const container = document.getElementById('comble-game-state');

        if (container) {
            container.outerHTML = data.html;
        }

        initGuessForm();
    } catch (e) {
        activityToken = null;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    initGuessForm();

    if (window.self !== window.top) {
        bootDiscordActivity();
    }

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
