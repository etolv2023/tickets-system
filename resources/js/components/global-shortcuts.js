const isEditable = (target) => target instanceof HTMLElement
    && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

/**
 * Keyboard shortcuts shared by every authenticated screen.
 *
 * `/` and Ctrl/Cmd+K focus the global search. Escape clears focus first and
 * then lets the existing overlay handlers close whatever remains open.
 */
export default function registerGlobalShortcuts() {
    document.addEventListener('keydown', (event) => {
        const search = document.querySelector('.topbar__search input[type="search"]');
        const wantsSearch = event.key === '/'
            || ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k');

        if (wantsSearch && !isEditable(event.target) && search instanceof HTMLInputElement) {
            event.preventDefault();
            search.focus();
            search.select();

            return;
        }

        if (event.key === 'Escape' && document.activeElement === search) {
            search.blur();
        }
    });
}
