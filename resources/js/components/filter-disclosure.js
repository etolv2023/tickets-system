/* Turn every dense GET filter form into the same compact interaction.
 *
 * Screens historically invented their own two- and three-row filter bars.
 * This presentation-only enhancement keeps the primary search/actions visible
 * and puts the narrowing controls behind one predictable button. Form names,
 * values and submission are untouched. */
export default function registerFilterDisclosures() {
    const enhance = (form) => {
        if (form.matches('.filters--tickets, .filters--calendar') || form.dataset.compactFilters === 'off') {
            return;
        }

        const controls = form.querySelectorAll('input:not([type="hidden"]), select, [data-combobox]');

        if (controls.length < 4) {
            return;
        }

        const existingBar = form.querySelector(':scope > .filters__bar');
        const existingNarrow = form.querySelector(':scope > .filters__narrow');
        const bar = existingBar ?? document.createElement('div');
        const panel = document.createElement('div');
        const toggle = document.createElement('button');

        if (!existingBar) {
            bar.className = 'filters__bar filters__bar--compact';
            form.prepend(bar);
        }

        panel.className = 'filters__disclosure-panel';
        panel.hidden = true;
        toggle.type = 'button';
        toggle.className = 'btn btn--secondary filters__disclosure-toggle';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<span aria-hidden="true">☷</span><span>الفلاتر</span>';

        if (existingNarrow) {
            panel.append(existingNarrow);
        } else {
            [...form.children].forEach((child) => {
                if (child === bar || child === panel || child.matches('input[type="hidden"]')) return;

                const isSearch = child.matches('input[type="search"]');
                const isAction = child.matches('button, a.btn');

                (isSearch || isAction ? bar : panel).append(child);
            });
        }

        bar.append(toggle);
        form.append(panel);
        form.classList.add('filters--disclosure');

        toggle.addEventListener('click', () => {
            panel.hidden = !panel.hidden;
            toggle.setAttribute('aria-expanded', String(!panel.hidden));
            form.classList.toggle('filters--open', !panel.hidden);
        });
    };

    document.querySelectorAll('form.filters').forEach(enhance);
}
