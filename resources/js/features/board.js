import Sortable from 'sortablejs';

/**
 * Drag-and-drop reordering for subtasks (F08).
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
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

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
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ ids }),
            }).catch(() => {
                // The order is already correct on screen; a failed save means the
                // next load will disagree, so say so rather than pretend.
                list.dispatchEvent(new CustomEvent('reorder-failed', { bubbles: true }));
            });
        },
    });
}

document.addEventListener('DOMContentLoaded', mountSubtaskSorting);
