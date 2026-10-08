/* Column visibility for every genuinely wide table. The first column stays
 * visible as the row identity; the rest are optional and persisted per URL
 * and table position. Ticket index has its richer server-authored picker. */
export default function registerTableColumnPickers() {
    document.querySelectorAll('.table-wrap').forEach((wrap, tableIndex) => {
        const table = wrap.querySelector(':scope > table.table');
        const headers = table ? [...table.querySelectorAll(':scope > thead > tr:first-child > th')] : [];

        if (!table || headers.length < 6
            || headers.some((header) => header.colSpan > 1 || header.rowSpan > 1)
            || wrap.closest('.page')?.querySelector('.table-columns')) return;

        const key = `table-columns:${window.location.pathname}:${tableIndex}`;
        let hidden = [];

        try {
            hidden = JSON.parse(localStorage.getItem(key)) ?? [];
        } catch {
            hidden = [];
        }

        const tools = document.createElement('div');
        const picker = document.createElement('div');
        const button = document.createElement('button');
        const menu = document.createElement('div');

        tools.className = 'table-tools';
        picker.className = 'table-columns';
        button.type = 'button';
        button.className = 'btn btn--secondary btn--sm';
        button.setAttribute('aria-expanded', 'false');
        button.textContent = 'الأعمدة';
        menu.className = 'table-columns__menu';
        menu.hidden = true;

        const apply = () => {
            headers.forEach((_, index) => {
                table.querySelectorAll(`tr > :nth-child(${index + 1})`).forEach((cell) => {
                    cell.classList.toggle('table__column--hidden', hidden.includes(index));
                });
            });
            try {
                localStorage.setItem(key, JSON.stringify(hidden));
            } catch {
                // Visibility still works for this visit when storage is blocked.
            }
        };

        headers.slice(1).forEach((header, offset) => {
            const index = offset + 1;
            const label = document.createElement('label');
            const checkbox = document.createElement('input');
            const text = document.createElement('span');

            label.className = 'table-columns__option';
            checkbox.type = 'checkbox';
            checkbox.checked = !hidden.includes(index);
            text.textContent = header.textContent.trim() || `عمود ${index + 1}`;
            checkbox.addEventListener('change', () => {
                hidden = checkbox.checked
                    ? hidden.filter((item) => item !== index)
                    : [...hidden, index];
                apply();
            });
            label.append(checkbox, text);
            menu.append(label);
        });

        button.addEventListener('click', () => {
            menu.hidden = !menu.hidden;
            button.setAttribute('aria-expanded', String(!menu.hidden));
        });
        document.addEventListener('click', (event) => {
            if (!picker.contains(event.target)) {
                menu.hidden = true;
                button.setAttribute('aria-expanded', 'false');
            }
        });

        picker.append(button, menu);
        tools.append(picker);
        wrap.before(tools);
        apply();
    });
}
