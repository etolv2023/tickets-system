/** @type {import('tailwindcss').Config} */
export default {
    // Dark mode is driven by tokens.css, not by dark: utilities. This selector
    // exists only for the rare case a utility genuinely needs to branch.
    darkMode: ['selector', '[data-theme="dark"]'],
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/View/Components/**/*.php',
    ],
    theme: {
        // Utilities read the same custom properties the CSS files do, so
        // tokens.css stays the single source of truth (CLAUDE.md § 3).
        extend: {
            colors: {
                urgent: 'var(--c-urgent)',
                high: 'var(--c-high)',
                medium: 'var(--c-medium)',
                low: 'var(--c-low)',
                resolved: 'var(--c-resolved)',
                blocked: 'var(--c-blocked)',
                bug: 'var(--c-bug)',
                inquiry: 'var(--c-inquiry)',
                feature: 'var(--c-feature)',
                module: 'var(--c-module)',
                bg: 'var(--bg)',
                surface: 'var(--surface)',
                'surface-2': 'var(--surface-2)',
                'surface-3': 'var(--surface-3)',
                border: 'var(--border)',
                'border-strong': 'var(--border-strong)',
                text: 'var(--text)',
                'text-muted': 'var(--text-muted)',
                'text-subtle': 'var(--text-subtle)',
            },
            borderRadius: {
                DEFAULT: 'var(--radius)',
                sm: 'var(--radius-sm)',
            },
            fontFamily: {
                sans: 'var(--font-ar)',
                mono: 'var(--font-mono)',
            },
            fontSize: {
                xs: 'var(--text-xs)',
                sm: 'var(--text-sm)',
                md: 'var(--text-md)',
                base: 'var(--text-base)',
                lg: 'var(--text-lg)',
                xl: 'var(--text-xl)',
                '2xl': 'var(--text-2xl)',
            },
            boxShadow: {
                sm: 'var(--shadow-sm)',
                md: 'var(--shadow-md)',
            },
        },
    },
    plugins: [],
};
