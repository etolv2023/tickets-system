import Alpine from 'alpinejs';
import editor from './components/editor';
import uploader from './components/uploader';
import lightbox from './components/lightbox';
import pointMatrix from './components/point-matrix';

// Theme: tokens.css already follows prefers-color-scheme on its own. This store
// only lets a user override the OS choice and remembers it. The initial value is
// applied inline in the layout head, before paint, to avoid a wrong-theme flash.
Alpine.store('theme', {
    set(value) {
        if (value === 'system') {
            delete document.documentElement.dataset.theme;
            localStorage.removeItem('theme');
        } else {
            document.documentElement.dataset.theme = value;
            localStorage.setItem('theme', value);
        }
    },

    toggle() {
        const isDark =
            document.documentElement.dataset.theme === 'dark' ||
            (!document.documentElement.dataset.theme &&
                window.matchMedia('(prefers-color-scheme: dark)').matches);

        this.set(isDark ? 'light' : 'dark');
    },
});

Alpine.data('editor', editor);
Alpine.data('uploader', uploader);
Alpine.data('lightbox', lightbox);
Alpine.data('pointMatrix', pointMatrix);

window.Alpine = Alpine;
Alpine.start();
