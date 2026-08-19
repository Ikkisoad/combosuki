function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function showDropError(board, message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger py-1 px-2 mt-2';
    alert.textContent = message;
    board.prepend(alert);
    setTimeout(() => alert.remove(), 4000);
}

function setupBulkSave({ tableId, saveButtonId, statusId, rowAttr, payloadKey }) {
    const table = document.getElementById(tableId);
    const saveBtn = document.getElementById(saveButtonId);
    const status = document.getElementById(statusId);

    if (! table || ! saveBtn) {
        return;
    }

    saveBtn.addEventListener('click', function () {
        const payload = {};

        table.querySelectorAll(`tr[${rowAttr}]`).forEach((row) => {
            const id = row.getAttribute(rowAttr);
            const fields = {};

            row.querySelectorAll('[data-field]').forEach((field) => {
                fields[field.dataset.field] = field.value === '' ? null : field.value;
            });

            payload[id] = fields;
        });

        saveBtn.disabled = true;
        status.textContent = 'Saving…';
        status.className = 'small text-white-50';

        fetch(table.dataset.saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ [payloadKey]: payload }),
        })
            .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (! ok) {
                    status.textContent = data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : (data.message || 'Could not save changes.');
                    status.className = 'small text-danger';
                    return;
                }

                status.textContent = 'Saved.';
                status.className = 'small text-success';
                setTimeout(() => { status.textContent = ''; }, 3000);
            })
            .catch(() => {
                status.textContent = 'Could not save changes.';
                status.className = 'small text-danger';
            })
            .finally(() => {
                saveBtn.disabled = false;
            });
    });
}

document.addEventListener('DOMContentLoaded', function () {
    setupBulkSave({
        tableId: 'pages-table',
        saveButtonId: 'save-pages-btn',
        statusId: 'save-pages-status',
        rowAttr: 'data-page-id',
        payloadKey: 'pages',
    });

    setupBulkSave({
        tableId: 'categories-table',
        saveButtonId: 'save-categories-btn',
        statusId: 'save-categories-status',
        rowAttr: 'data-category-id',
        payloadKey: 'categories',
    });

    const board = document.getElementById('combo-board');

    if (! board) {
        return;
    }

    const listId = board.dataset.listId;
    let draggedCard = null;

    board.querySelectorAll('.combo-card').forEach((card) => {
        card.addEventListener('dragstart', function () {
            draggedCard = card;
            card.classList.add('dragging');
        });

        card.addEventListener('dragend', function () {
            card.classList.remove('dragging');
            draggedCard = null;
        });
    });

    board.querySelectorAll('.category-dropzone').forEach((zone) => {
        zone.addEventListener('dragover', function (event) {
            event.preventDefault();
            zone.classList.add('drop-target');
        });

        zone.addEventListener('dragleave', function () {
            zone.classList.remove('drop-target');
        });

        zone.addEventListener('drop', function (event) {
            event.preventDefault();
            zone.classList.remove('drop-target');

            if (! draggedCard) {
                return;
            }

            const comboId = draggedCard.dataset.comboId;
            const categoryId = zone.dataset.categoryId || '';
            const card = draggedCard;

            fetch(`/lists/${listId}/entries/${comboId}/category`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    list_category_idlist_category: categoryId === '' ? null : Number(categoryId),
                }),
            })
                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (! ok) {
                        showDropError(board, data.error || data.message || 'Could not move that combo.');
                        return;
                    }

                    zone.querySelector('.combo-list')?.appendChild(card);
                })
                .catch(() => showDropError(board, 'Could not move that combo.'));
        });
    });
});
