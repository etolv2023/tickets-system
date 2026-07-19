import Sortable from 'sortablejs';

/**
 * Subtask interactions: reordering (F08) and one-click status.
 *
 * A separate Vite entry: SortableJS has no business loading on the login page
 * (CLAUDE.md § 3 / PLAN.md § 8). Only screens that actually drag pull this in.
 *
 * The buttons remain the primary path — dragging is the shortcut, not the
 * requirement (F12.1).
 */
function mountSubtaskSorting() {
    const list = document.querySelector('[data-sortable="subtasks"]');

    if (!list) {
        return;
    }

    const url = list.dataset.reorderUrl;

    Sortable.create(list, {
        animation: 120,
        // Only the grip starts a drag, so clicking a row still works normally.
        handle: '[data-drag-handle]',
        ghostClass: 'subtask--ghost',
        dragClass: 'subtask--dragging',

        onEnd() {
            const ids = [...list.querySelectorAll('[data-subtask-id]')].map((el) =>
                Number(el.dataset.subtaskId)
            );

            fetch(url, {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify({ ids }),
            }).catch(() => {
                // The order is already correct on screen; a failed save means the
                // next load will disagree, so say so rather than pretend.
                list.dispatchEvent(new CustomEvent('reorder-failed', { bubbles: true }));
            });
        },
    });
}

export function jsonHeaders() {
    return {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        Accept: 'application/json',
    };
}

/**
 * One-click subtask status. Exported because the board mounts the same control
 * inside each card's accordion — which is why this takes a root rather than
 * querying the document itself.
 *
 * Optimistic with rollback (the shape calendar.js uses): the select keeps the
 * new value while the request is in flight and snaps back if the server says
 * no, so a refusal is never silently swallowed.
 */
export function mountSubtaskStatus(root = document) {
    root.querySelectorAll('[data-subtask-status]').forEach((select) => {
        // board.js imports this module, so its own DOMContentLoaded listener
        // runs too and every select would end up with two change handlers —
        // two identical PATCHes, the second refused by PreventDuplicateSubmit.
        // The change did save; the error was the echo.
        if (select.dataset.statusBound) {
            return;
        }

        select.dataset.statusBound = 'true';

        select.addEventListener('change', async () => {
            const previous = select.dataset.current ?? '';
            const url = select.dataset.subtaskStatus;

            select.disabled = true;

            try {
                const response = await fetch(url, {
                    method: 'PATCH',
                    headers: jsonHeaders(),
                    body: JSON.stringify({ status: select.value }),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message ?? 'الحفظ فشل.');
                }

                select.dataset.current = select.value;
                announce(select, payload);
            } catch (error) {
                select.value = previous;
                announce(select, { message: error.message });
            } finally {
                select.disabled = false;
            }
        });
    });
}

/**
 * Writes into the row's live region rather than alert(): a refusal has to be
 * readable without stealing focus, and a screen reader has to hear it.
 */
function announce(select, payload) {
    const row = select.closest('[data-subtask-id]');
    const region = row?.querySelector('[data-subtask-message]');

    if (region) {
        region.textContent = payload.message ?? '';
    }

    // The ticket-level counter ("3/7") lives outside the row.
    if (payload.done !== undefined) {
        document.querySelectorAll('[data-subtask-counter]').forEach((el) => {
            el.textContent = `${payload.done}/${payload.total}`;
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    mountSubtaskSorting();
    mountSubtaskStatus();
});
