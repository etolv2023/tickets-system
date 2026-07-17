/**
 * Calendar drag-to-reschedule (F13).
 *
 * A separate Vite entry — this must not load on any other screen (PLAN.md § 8).
 * Uses the native HTML5 drag API rather than a library: the grid is already
 * ours, and this is the only interaction on it.
 */

function moveSubtask(id, dueDate) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    return fetch(`/subtasks/${id}/schedule`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            Accept: 'application/json',
        },
        body: JSON.stringify({ due_date: dueDate }),
    }).then((r) => (r.ok ? r.json() : Promise.reject(r)));
}

function mount() {
    const grid = document.querySelector('[data-calendar]');

    if (!grid) {
        return;
    }

    let dragged = null;

    grid.addEventListener('dragstart', (e) => {
        const item = e.target.closest('[data-subtask-id]');

        if (!item) {
            e.preventDefault();
            return;
        }

        dragged = item;
        e.dataTransfer.effectAllowed = 'move';
        // Firefox needs something in the payload or the drag never starts.
        e.dataTransfer.setData('text/plain', item.dataset.subtaskId);
    });

    grid.addEventListener('dragover', (e) => {
        const cell = e.target.closest('[data-date]');

        if (cell && dragged) {
            e.preventDefault();
            cell.classList.add('cal__day--drop');
        }
    });

    grid.addEventListener('dragleave', (e) => {
        e.target.closest('[data-date]')?.classList.remove('cal__day--drop');
    });

    grid.addEventListener('drop', (e) => {
        const cell = e.target.closest('[data-date]');

        if (!cell || !dragged) {
            return;
        }

        e.preventDefault();
        cell.classList.remove('cal__day--drop');

        const id = dragged.dataset.subtaskId;
        const date = cell.dataset.date;
        const item = dragged;
        dragged = null;

        if (item.dataset.dueDate === date) {
            return;
        }

        // Move it optimistically so the drag feels immediate, then reconcile.
        const origin = item.parentElement;
        cell.querySelector('[data-items]')?.appendChild(item);

        moveSubtask(id, date)
            .then((data) => {
                item.dataset.dueDate = data.due_date;

                // F13: crossing the SLA is allowed, but the person has to be
                // told — silently letting it slide is how deadlines get missed.
                if (data.warning && !window.confirm(`${data.warning}\n\nنسيبها كده؟`)) {
                    return moveSubtask(id, item.dataset.originalDue || date).then(() => {
                        window.location.reload();
                    });
                }
            })
            .catch(() => {
                // The save failed; putting it back is more honest than leaving
                // the screen showing a date the server never accepted.
                origin?.appendChild(item);
                window.alert('مقدرناش نغيّر الموعد. حدّث الصفحة وجرّب تاني.');
            });
    });
}

document.addEventListener('DOMContentLoaded', mount);
