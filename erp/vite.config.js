import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css', 
                'resources/js/app.js',
                'resources/js/pages/board-show.js',
                'resources/js/notifications.js'
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        hmr: {
            overlay: false, // This disables the red error overlay
        },
    },
});
