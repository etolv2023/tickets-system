export default {
    plugins: {
        // Required so @import in app.css is inlined before Tailwind runs.
        'postcss-import': {},
        tailwindcss: {},
        autoprefixer: {},
    },
};
