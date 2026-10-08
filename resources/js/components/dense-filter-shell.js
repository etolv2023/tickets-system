/* A safe disclosure layer for dense filter forms.
 *
 * Unlike the retired implementation, this never moves controls or changes
 * their grouping. It only hides/shows existing direct filter blocks, so date
 * ranges, combobox scopes and Alpine ownership remain exactly where Blade put
 * them. */
export default function registerDenseFilterShells() {
    const manual = '.filters--tickets, .filters--calendar, .reports-ledger-filters';

    document.querySelectorAll('form.filters').forEach((form) => {
        if (form.matches(manual) || form.querySelector('.filters__narrow--panel')) return;

        const controls = form.querySelectorAll('input:not([type="hidden"]), select');
        if (controls.length < 4) return;

        const existingBar = form.querySelector(':scope > .filters__bar');
        const existingNarrow = form.querySelector(':scope > .filters__narrow');
        const controlsRoot = existingBar ?? form;
        const targets = existingNarrow
            ? [existingNarrow]
            : [...controlsRoot.children].filter((child) => {
                if (child.matches('input[type="hidden"], input[type="search"], button, a.btn')) return false;
                return child.matches('input, select, .filters__combobox, .filters__group, .filters__range, .field')
                    || child.querySelector('select, input:not([type="hidden"])');
            });

        if (!targets.length) return;

        const activeCount = [...form.elements].filter((control) => {
            if (!control.name || ['q', 'page', '_token', '_method'].includes(control.name)) return false;
            if (control instanceof HTMLSelectElement) return control.selectedIndex > 0;
            if (['checkbox', 'radio'].includes(control.type)) return control.checked;
            return control.value !== '' && control.value !== 'all' && control.value !== 'any';
        }).length;
        const button = document.createElement('button');
        let open = false;

        button.type = 'button';
        button.className = 'btn btn--secondary dense-filter-toggle';
        button.innerHTML = `<span aria-hidden="true">☷</span><span>الفلاتر</span>${activeCount ? `<span class="filters__active-count">${activeCount}</span>` : ''}`;

        const render = () => {
            targets.forEach((target) => { target.hidden = !open; });
            button.setAttribute('aria-expanded', String(open));
            button.classList.toggle('dense-filter-toggle--open', open);
        };

        button.addEventListener('click', () => {
            open = !open;
            render();
        });

        controlsRoot.append(button);
        form.classList.add('filters--dense-shell');
        render();
    });
}
