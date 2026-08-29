/**
 * Stops a form being submitted twice.
 *
 * Every non-GET form in the app is covered by one delegated listener rather
 * than by remembering to wire each button — a guard you have to opt into is a
 * guard someone forgets on the one form that mattered.
 *
 * What it does on the first submit:
 *   - marks the form as in-flight and swallows every later submit event
 *   - disables the submit controls and puts them in a loading state
 *
 * What it deliberately does NOT do:
 *   - block a form the browser refused (failed HTML5 validation never reaches
 *     submit, but an invalid form that is corrected must stay usable)
 *   - stay locked forever: leaving the page via bfcache and coming back would
 *     otherwise show a permanently dead button
 *   - ★ (2026-08-29) keep a form locked when the submit was CANCELLED — see
 *     the unlock-on-prevented note in the listener
 *
 * The server has its own duplicate guard. This one is the fast path; that one
 * is the one that actually holds when JavaScript is off or slow.
 */

const IN_FLIGHT = 'data-submitting';

function submitControls(form) {
    const inside = Array.from(form.querySelectorAll('button, input[type="submit"]')).filter(
        (el) => !el.type || el.type === 'submit',
    );

    // A button can sit outside its form via form="id".
    const outside = form.id
        ? Array.from(document.querySelectorAll(`[form="${CSS.escape(form.id)}"]`)).filter(
              (el) => !el.type || el.type === 'submit',
          )
        : [];

    return [...inside, ...outside];
}

function lock(form) {
    form.setAttribute(IN_FLIGHT, '');

    submitControls(form).forEach((el) => {
        el.classList.add('is-loading');
        // aria-disabled rather than disabled on the element that was clicked:
        // a disabled submit button is not included in the POST body, which
        // would drop a form that relies on the button's own name/value.
        el.setAttribute('aria-disabled', 'true');
        el.disabled = true;
    });
}

function unlock(form) {
    form.removeAttribute(IN_FLIGHT);

    submitControls(form).forEach((el) => {
        el.classList.remove('is-loading');
        el.removeAttribute('aria-disabled');
        el.disabled = false;
    });
}

export default function registerSubmitGuard() {
    // Capture phase: this must run before any other submit handler decides to
    // do work of its own.
    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || form.dataset.noSubmitGuard !== undefined) {
                return;
            }

            if (form.hasAttribute(IN_FLIGHT)) {
                event.preventDefault();
                event.stopImmediatePropagation();

                return;
            }

            lock(form);

            /*
             * ★ (2026-08-29) Unlock again if something cancelled this submit.
             *
             * This listener is on the CAPTURE phase, so it locks before any
             * other handler has had a say — and every delete button in this app
             * is an inline onsubmit="return confirm(...)", which runs later and
             * can still call the submit off. Answering "لأ" to that dialog (or
             * a browser that suppressed it, which Chrome does after a page
             * shows a few) therefore left the form locked and the button dead
             * until a reload, with nothing on screen explaining why. It reads
             * exactly like "the button does not work".
             *
             * defaultPrevented is only final once dispatch is over, hence the
             * tick. On a submit that DOES go through, the page is navigating
             * and this either never runs or finds nothing to undo.
             */
            setTimeout(() => {
                if (event.defaultPrevented) {
                    unlock(form);
                }
            }, 0);
        },
        true,
    );

    // Restoring from the back/forward cache re-shows the old DOM, locked
    // buttons and all. Clear them so the page is usable again.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            document.querySelectorAll(`form[${IN_FLIGHT}]`).forEach(unlock);
        }
    });
}
