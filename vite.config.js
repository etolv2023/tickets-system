import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // Separate entries per PLAN.md § 8: SortableJS must not load on the
            // login page, and the calendar bundle must not load anywhere else.
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/features/board.js',
                'resources/js/features/calendar.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
