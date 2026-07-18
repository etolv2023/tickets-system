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
