document.addEventListener('DOMContentLoaded', function () {
    const catalogEl = document.getElementById('tier-list-catalog');
    const gameSelect = document.getElementById('tier-list-game');
    const board = document.getElementById('tier-board');
    const pool = document.getElementById('unranked-pool');
    const form = document.getElementById('tier-list-form');
    const entriesContainer = document.getElementById('tier-list-entries-fields');

    if (! catalogEl || ! gameSelect || ! board || ! pool || ! form || ! entriesContainer) {
        return;
    }

    const catalog = JSON.parse(catalogEl.textContent || '{}');
    let draggedCard = null;

    function makeCard(character, resourceValue) {
        const card = document.createElement('div');
        card.className = 'character-card';
        card.draggable = true;
        card.title = character.name;
        card.dataset.characterId = character.idcharacter;

        if (resourceValue) {
            card.dataset.resourceValueId = resourceValue.idResources_values;
        }

        const iconWrap = document.createElement('div');
        iconWrap.className = 'character-icon-wrap';

        if (character.image) {
            const img = document.createElement('img');
            img.src = character.image;
            img.alt = character.name;
            img.className = 'character-icon';
            iconWrap.appendChild(img);
        }

        if (resourceValue && resourceValue.icon) {
            const badge = document.createElement('img');
            badge.src = resourceValue.icon;
            badge.alt = resourceValue.value;
            badge.className = 'resource-badge';
            iconWrap.appendChild(badge);
        }

        card.appendChild(iconWrap);

        const label = document.createElement('div');
        label.className = 'small text-center character-card-name';
        label.textContent = character.name;
        label.title = character.name;
        card.appendChild(label);

        card.addEventListener('dragstart', function () {
            draggedCard = card;
            card.classList.add('dragging');
        });

        card.addEventListener('dragend', function () {
            card.classList.remove('dragging');
            draggedCard = null;
        });

        return card;
    }

    function getDragAfterElement(zone, x, y) {
        const cards = [...zone.querySelectorAll('.character-card:not(.dragging)')];

        return cards.reduce((closest, card) => {
            const box = card.getBoundingClientRect();
            const withinRow = y >= box.top && y <= box.bottom;
            const offset = x - box.left - box.width / 2;

            if (withinRow && offset < 0 && offset > closest.offset) {
                return { offset, element: card };
            }

            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    }

    function setupDropzone(zone) {
        zone.addEventListener('dragover', function (event) {
            event.preventDefault();
            zone.classList.add('drop-target');

            if (! draggedCard) {
                return;
            }

            const afterElement = getDragAfterElement(zone, event.clientX, event.clientY);

            if (afterElement) {
                zone.insertBefore(draggedCard, afterElement);
            } else {
                zone.appendChild(draggedCard);
            }
        });

        zone.addEventListener('dragleave', function () {
            zone.classList.remove('drop-target');
        });

        zone.addEventListener('drop', function (event) {
            event.preventDefault();
            zone.classList.remove('drop-target');
        });
    }

    function setupBucket(bucket) {
        bucket.addEventListener('dragover', function (event) {
            event.preventDefault();
            bucket.classList.add('drop-target');
        });

        bucket.addEventListener('dragleave', function () {
            bucket.classList.remove('drop-target');
        });

        bucket.addEventListener('drop', function (event) {
            event.preventDefault();
            bucket.classList.remove('drop-target');

            if (! draggedCard) {
                return;
            }

            const targetZone = document.querySelector(`.tier-dropzone[data-tier="${bucket.dataset.tier}"]`);

            if (targetZone) {
                targetZone.appendChild(draggedCard);
            }
        });
    }

    document.querySelectorAll('.tier-dropzone').forEach(setupDropzone);
    document.querySelectorAll('.tier-bucket').forEach(setupBucket);

    gameSelect.addEventListener('change', function () {
        pool.innerHTML = '';
        document.querySelectorAll('#tier-board .tier-dropzone').forEach((zone) => { zone.innerHTML = ''; });

        const gameData = catalog[gameSelect.value] || { characters: [], resource: null };
        const characters = gameData.characters || [];
        const resource = gameData.resource || null;

        if (characters.length === 0) {
            board.style.display = 'none';
            return;
        }

        if (resource) {
            characters.forEach((character) => {
                (character.resourceValues || []).forEach((value) => pool.appendChild(makeCard(character, value)));
            });
        } else {
            characters.forEach((character) => pool.appendChild(makeCard(character)));
        }

        board.style.display = '';
    });

    form.addEventListener('submit', function (event) {
        if (form.dataset.confirmed === '1') {
            form.dataset.confirmed = '';
            return;
        }

        event.preventDefault();

        window.confirmDialog('Once submitted, this tier list cannot be edited. Submit anyway?').then(function (ok) {
            if (! ok) {
                return;
            }

            entriesContainer.innerHTML = '';

            let index = 0;

            document.querySelectorAll('#tier-board .tier-dropzone').forEach((zone) => {
                const tier = zone.dataset.tier;

                zone.querySelectorAll('.character-card').forEach((card) => {
                    const characterInput = document.createElement('input');
                    characterInput.type = 'hidden';
                    characterInput.name = `entries[${index}][character_idcharacter]`;
                    characterInput.value = card.dataset.characterId;

                    const tierInput = document.createElement('input');
                    tierInput.type = 'hidden';
                    tierInput.name = `entries[${index}][tier]`;
                    tierInput.value = tier;

                    entriesContainer.appendChild(characterInput);
                    entriesContainer.appendChild(tierInput);

                    if (card.dataset.resourceValueId) {
                        const valueInput = document.createElement('input');
                        valueInput.type = 'hidden';
                        valueInput.name = `entries[${index}][resources_values_idResources_values]`;
                        valueInput.value = card.dataset.resourceValueId;
                        entriesContainer.appendChild(valueInput);
                    }

                    index++;
                });
            });

            form.dataset.confirmed = '1';
            form.requestSubmit();
        });
    });
});
