import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/floor-plan.js',
                'resources/js/restaurant-global-notify.js',
                'resources/js/customer-table-realtime.js',
                'resources/js/dashboard-floor.js',
                'resources/js/staff-pos-order.js',
                'resources/js/guest-menu.js',
                'resources/js/catalog-admin.js',
            ],
            refresh: true,
        }),
    ],
});
