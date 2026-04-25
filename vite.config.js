import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const allowedDevOrigins = [
    /^https:\/\/photobooth-crm\.test$/,
    /^https:\/\/[a-z0-9-]+\.photobooth-crm\.test$/,
    /^https:\/\/photobooth-crm-vite\.test$/,
];

export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: 'https://photobooth-crm-vite.test',
        hmr: {
            host: 'photobooth-crm-vite.test',
            protocol: 'wss',
        },
        cors: {
            origin: allowedDevOrigins,
        },
        allowedHosts: ['photobooth-crm-vite.test', '.photobooth-crm.test'],
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
