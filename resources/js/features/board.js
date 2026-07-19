import Sortable from 'sortablejs';
import { jsonHeaders, mountSubtaskStatus } from './subtasks.js';

/**
 * Dragging a ticket between board columns (F12.1).
 *
 * The بدأت / خلصت buttons remain the primary path — dragging is the shortcut,
 * not the requirement. Every guard the buttons obey applies here too, because
 * the endpoint routes through the same service: an unfinished subtask refuses
 * the drop and the card goes back where it came from.
 */
function mountBoardDragging() {
    document.querySelectorAll('[data-board-list]').forEach((list) => {
        Sortable.create(list, {
            group: {
                // Scoped per lane, not per board: the team board repeats the
                // same four column keys in every swimlane, and a card must not
                // jump between lanes — that would silently reassign it.
                name: list.dataset.boardGroup,
                // The server would refuse these anyway; refusing here means the
                // card never leaves the column in the first place.
                put: (to, from, dragged) => columnsFor(dragged).includes(to.el.dataset.boardList),
            },
            animation: 120,
            handle: '[data-drag-handle]',
            ghostClass: 'tcard--ghost',
            dragClass: 'tcard--dragging',

            onStart: (event) => {
                const open = columnsFor(event.item);

                document.querySelectorAll('[data-board-list]').forEach((column) => {
                    column.closest('.board__column')
                        ?.classList.toggle('board__column--closed', !open.includes(column.dataset.boardList));
                });
            },

            onEnd: (event) => {
                document.querySelectorAll('.board__column--closed')
                    .forEach((column) => column.classList.remove('board__column--closed'));

                move(event);
            },
        });
    });
}

function columnsFor(card) {
    return (card.dataset.dropColumns ?? '').split(' ').filter(Boolean);
}

async function move(event) {
    const { item, to, from, oldIndex } = event;

    if (to === from) {
        return;
    }

    const url = item.dataset.moveUrl;
    const column = to.dataset.boardList;

    try {
        const response = await fetch(url, {
            method: 'PATCH',
            headers: jsonHeaders(),
            body: JSON.stringify({ column }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || payload.ok === false) {
            throw new Error(payload.message ?? 'النقلة اترفضت.');
        }

        // Accepted, but the ticket did not end up in this column — a side
        // finished while another is still working. Put the card back and say
        // why, rather than reloading it out from under the pointer with no
        // explanation.
        if (payload.landed === false) {
            from.insertBefore(item, from.children[oldIndex] ?? null);
            report(payload.message);

            return;
        }

        if (payload.moved === false) {
            return;
        }

        // The column counts and the card's own status badge are now stale.
        // Reloading is honest: a move can cascade (every side done → dev_done →
        // testing), and guessing the end state on the client would sometimes
        // show a status the server never set.
        window.location.reload();
    } catch (error) {
        // Put it back exactly where it was, then say why.
        from.insertBefore(item, from.children[oldIndex] ?? null);
        report(error.message);
    }
}

/**
 * Writes into the board's live region rather than alert(): a refusal has to be
 * readable without stealing focus, and a screen reader has to hear it.
 */
function report(message) {
    const region = document.querySelector('[data-board-message]');

    if (region) {
        region.textContent = message;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    mountBoardDragging();
    mountSubtaskStatus();
});
