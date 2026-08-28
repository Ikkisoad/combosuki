import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/list-manage.js', 'resources/js/randomizer.js', 'resources/js/comble.js', 'resources/js/activity-comble.js', 'resources/js/timeline.js', 'resources/js/tier-list-maker.js', 'resources/js/lists-show.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
