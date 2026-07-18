import Alpine from 'alpinejs';
import editor from './components/editor';
import uploader from './components/uploader';
import lightbox from './components/lightbox';
import pointMatrix from './components/point-matrix';
import combobox from './components/combobox';
import bell from './components/bell';
import registerSubmitGuard from './components/submit-guard';

// Before Alpine, and before anything else can attach a submit handler: a
// double-click on "إرسال" used to post the same comment three times.
registerSubmitGuard();

// Theme: tokens.css already follows prefers-color-scheme on its own. This store
// only lets a user override the OS choice and remembers it. The initial value is
// applied inline in the layout head, before paint, to avoid a wrong-theme flash.
const prefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

const resolveDark = () => {
    const chosen = document.documentElement.dataset.theme;
    return chosen ? chosen === 'dark' : prefersDark();
};

Alpine.store('theme', {
    // Mirrors the resolved theme so the topbar can swap sun/moon reactively.
    // The DOM stays the source of truth; this only follows it.
    dark: resolveDark(),

    set(value) {
        if (value === 'system') {
            delete document.documentElement.dataset.theme;
            localStorage.removeItem('theme');
        } else {
            document.documentElement.dataset.theme = value;
            localStorage.setItem('theme', value);
        }

        this.dark = resolveDark();
    },

    toggle() {
        this.set(this.dark ? 'light' : 'dark');
    },
});

// Sidebar rail. Like the theme, the state lives as an attribute on <html> and
// is applied inline in the layout head, so a collapsed nav is collapsed on the
// first painted frame rather than snapping shut once Alpine boots.
Alpine.store('sidebar', {
    collapsed: document.documentElement.dataset.sidebar === 'collapsed',
    // Mobile only. The rail has no room to sit beside the content on a phone,
    // so below the breakpoint it becomes a drawer over it instead. Deliberately
    // NOT persisted: an overlay that covers the screen must never be what you
    // find waiting for you on the next page load.
    open: false,

    // The same media query layout.css and nav.css branch on. Read at call time,
    // not cached, so rotating a phone or resizing a window cannot strand the
    // nav in the wrong mode.
    get isMobile() {
        return window.matchMedia('(max-width: 60rem)').matches;
    },

    toggle() {
        if (this.isMobile) {
            this.setOpen(!this.open);

            return;
        }

        this.collapsed = !this.collapsed;

        if (this.collapsed) {
            document.documentElement.dataset.sidebar = 'collapsed';
            localStorage.setItem('sidebar', 'collapsed');
        } else {
            delete document.documentElement.dataset.sidebar;
            localStorage.setItem('sidebar', 'open');
        }
    },

    setOpen(value) {
        this.open = value;

        if (value) {
            document.documentElement.dataset.navOpen = '';
        } else {
            delete document.documentElement.dataset.navOpen;
        }
    },

    close() {
        this.setOpen(false);
    },
});

// Following a link inside the drawer navigates, which leaves the drawer open
// behind the new page for the one frame before unload — and open for real if
// the target is the current page. Closing on any nav click covers both.
document.addEventListener('click', (event) => {
    if (event.target.closest('#app-nav a')) {
        Alpine.store('sidebar').close();
    }
});

// Escape closes it, like any other overlay in the app.
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        Alpine.store('sidebar').close();
    }
});

// Crossing the breakpoint with the drawer open would otherwise leave the
// desktop rail stuck behind a backdrop.
window.matchMedia('(max-width: 60rem)').addEventListener('change', (event) => {
    if (!event.matches) {
        Alpine.store('sidebar').close();
    }
});

// Copy-to-clipboard for a single value, with a two-second "copied" state.
// navigator.clipboard needs a secure context; over plain http on a LAN address
// it is undefined, so fall back to the old selection trick rather than fail
// silently on exactly the machines this system runs on.
Alpine.data('copyValue', (value) => ({
    copied: false,

    async copy() {
        // The Clipboard API is not just absent over plain http — it also
        // rejects at call time (an unfocused document, a denied permission).
        // So the textarea fallback has to cover a throw, not only a missing
        // API, or the button silently does nothing.
        if (await this.viaClipboardApi(value) || this.viaTextarea(value)) {
            this.copied = true;
            setTimeout(() => (this.copied = false), 2000);
        }
    },

    async viaClipboardApi(text) {
        if (!navigator.clipboard || !window.isSecureContext) {
            return false;
        }

        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch (e) {
            return false;
        }
    },

    viaTextarea(text) {
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();

        try {
            return document.execCommand('copy');
        } catch (e) {
            return false;
        } finally {
            field.remove();
        }
    },
}));

Alpine.data('editor', editor);
Alpine.data('uploader', uploader);
Alpine.data('lightbox', lightbox);
Alpine.data('pointMatrix', pointMatrix);
Alpine.data('combobox', combobox);
Alpine.data('bell', bell);

window.Alpine = Alpine;
Alpine.start();
