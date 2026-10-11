import { t } from '../i18n';

/**
 * A ticket-page panel whose markup is fetched the first time its tab opens
 * (TicketPanelController). Used as:
 *
 *   x-data="lazyPanel(url)" x-effect="tab === 'code' && load()" x-html="html"
 *
 * The request state is kept out of Alpine's reactive data on purpose: x-effect
 * re-runs whenever something it read changes, so a reactive flag read inside
 * load() would turn a failed request into an endless retry. As it is, the only
 * thing the effect tracks is the tab, and a failure retries on the next open.
 */
export default function lazyPanel(url) {
    let state = 'idle';

    const note = (text) => {
        const p = document.createElement('p');

        p.className = 'field__hint';
        p.textContent = text;

        return p.outerHTML;
    };

    return {
        html: '',

        async load() {
            if (state !== 'idle') {
                return;
            }

            state = 'loading';
            this.html = note(t('جارٍ التحميل…'));

            try {
                const response = await fetch(url, {
                    headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                // Our own server-rendered, escaped Blade — the same markup the
                // page used to print inline. x-html initialises any Alpine in it.
                this.html = await response.text();
                state = 'done';
            } catch {
                this.html = note(t('تعذّر تحميل هذا القسم. افتح التبويب مرة أخرى للمحاولة.'));
                state = 'idle';
            }
        },
    };
}
