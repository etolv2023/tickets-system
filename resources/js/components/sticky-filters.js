/**
 * Every filter bar remembers where you left it.
 *
 * Filtering is a saved view, not a one-off: "تذاكري" or "بج + عاجل" is how a
 * person reads this system every morning, and re-picking it on every visit is
 * the tax. So the last state of each filter bar is kept per screen and put back
 * when you arrive at that screen with a bare url.
 *
 * WHAT IS SAVED, AND WHEN. Not "any url with a query string" — only a state the
 * user produced from the bar itself: submitting it, or clicking one of its own
 * links (تذاكري, مسح). That distinction is the whole point: opening a colleague's
 * filtered link shows you their view without overwriting yours.
 *
 * WHY "مسح" NEEDS NO SPECIAL CASE. It is a link inside the form, so clicking it
 * saves the state it produces — empty on /tickets, `view=month` on the calendar.
 * Next visit restores the cleared state, which is exactly what clearing meant.
 * Without this the restore would immediately undo the clear and the button would
 * look broken.
 *
 * The restore itself is NOT here — it runs inline in the layout head, before
 * first paint, or the list would render unfiltered for a frame and then jump.
 * This half only writes.
 */
const PREFIX = 'filters:';

// `page` is deliberately dropped: coming back to a screen should land you on
// its first page, not on page 7 of a list that has moved on since.
const IGNORED = ['page', '_token'];

/** Per user, not per browser — a shared machine must not hand you someone else's «تذاكري». */
const scope = () => document.querySelector('meta[name="user-id"]')?.content || '0';

const keyFor = (path) => `${PREFIX}${scope()}:${path}`;

const normalise = (params) => {
    IGNORED.forEach((name) => params.delete(name));

    // An empty select is "كل الحالات", i.e. no filter — storing it would make
    // every saved state look filtered and turn on the "3 فلاتر شغّالة" counter.
    for (const [name, value] of [...params.entries()]) {
        if (value === '') {
            params.delete(name);
        }
    }

    return params.toString();
};

const remember = (path, query) => {
    const key = keyFor(path);

    if (query) {
        localStorage.setItem(key, query);
    } else {
        localStorage.removeItem(key);
    }
};

export default function registerStickyFilters() {
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form.filters');

        if (!form || form.dataset.remember === 'off') {
            return;
        }

        remember(
            new URL(form.action, location.href).pathname,
            normalise(new URLSearchParams(new FormData(form)))
        );
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('form.filters a[href]');

        if (!link) {
            return;
        }

        const form = link.closest('form.filters');
        const target = new URL(link.href, location.href);

        // A link that leaves the screen — «تذكرة جديدة» sits in the same bar —
        // is not a filter state. Only links back to the list itself count.
        if (form.dataset.remember === 'off'
            || target.pathname !== new URL(form.action, location.href).pathname) {
            return;
        }

        remember(target.pathname, normalise(target.searchParams));
    });
}
