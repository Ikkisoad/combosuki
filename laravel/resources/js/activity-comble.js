import { DiscordSDK } from '@discord/embedded-app-sdk';

const statusEl = document.getElementById('activity-comble-status');
const gameEl = document.getElementById('activity-comble-game');

let activityToken = null;

function setStatus(message) {
    if (statusEl) {
        statusEl.textContent = message;
    }
}

function showGame() {
    if (statusEl) statusEl.style.display = 'none';
    if (gameEl) gameEl.style.display = '';
}

/** Attaches the Bearer token every /activity/comble/* API call (besides the token exchange itself) needs — see VerifyActivityToken. */
function authorizedFetch(url, options = {}) {
    return fetch(url, {
        ...options,
        headers: { ...options.headers, Authorization: `Bearer ${activityToken}` },
    });
}

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

/**
 * Cascading game -> character/type select behavior, identical to
 * resources/js/comble.js's initGuessForm() — see that file for the
 * reasoning behind the sticky-title fallback for the type select.
 */
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
 * Submits a guess via fetch (there is no full-page fallback here — the
 * Activity is JS-only) using the Bearer token instead of the cookie session
 * resources/js/comble.js's equivalent relies on.
 */
function submitGuessForm(form) {
    showGuessError('');

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : null;

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Guessing…';
    }

    authorizedFetch(form.action, {
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

async function loadState() {
    const response = await authorizedFetch('/activity/comble/state', { headers: { Accept: 'application/json' } });

    if (! response.ok) {
        setStatus("Couldn't load today's Comble puzzle. Please reopen the Activity.");
        return;
    }

    const data = await response.json();
    gameEl.innerHTML = data.html;
    initGuessForm();
    showGame();
}

/**
 * The Discord embedded-app auth handshake: authorize() (client-side, inside
 * Discord) returns a `code`; that code is exchanged server-side (see
 * ActivityAuthController — no redirect_uri needed for this embedded flow,
 * unlike the site's regular Discord sign-in). The response carries both the
 * short-lived signed token this page's own API calls use, and the raw
 * Discord access token, which authenticate() below needs to complete the
 * SDK's own session.
 */
async function boot() {
    const applicationId = document.querySelector('meta[name="discord-application-id"]')?.content;

    if (! applicationId) {
        setStatus('This page is not configured for Discord.');
        return;
    }

    try {
        const discordSdk = new DiscordSDK(applicationId);
        await discordSdk.ready();

        const { code } = await discordSdk.commands.authorize({
            client_id: applicationId,
            response_type: 'code',
            state: '',
            prompt: 'none',
            scope: ['identify'],
        });

        const tokenResponse = await fetch('/activity/comble/token', {
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
    } catch (e) {
        setStatus("Couldn't connect to Discord. Please reopen the Activity.");
        return;
    }

    await loadState();
}

document.addEventListener('DOMContentLoaded', function () {
    boot();

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
