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

    function makeCard(character) {
        const card = document.createElement('div');
        card.className = 'character-card';
        card.draggable = true;
        card.dataset.characterId = character.idcharacter;

        if (character.image) {
            const img = document.createElement('img');
            img.src = character.image;
            img.alt = character.name;
            card.appendChild(img);
        }

        const label = document.createElement('div');
        label.className = 'small text-center';
        label.textContent = character.name;
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

    document.querySelectorAll('.tier-dropzone').forEach(setupDropzone);

    gameSelect.addEventListener('change', function () {
        pool.innerHTML = '';
        document.querySelectorAll('#tier-board .tier-dropzone').forEach((zone) => { zone.innerHTML = ''; });

        const characters = catalog[gameSelect.value] || [];

        if (characters.length === 0) {
            board.style.display = 'none';
            return;
        }

        characters.forEach((character) => pool.appendChild(makeCard(character)));
        board.style.display = '';
    });

    form.addEventListener('submit', function () {
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

                index++;
            });
        });
    });
});
