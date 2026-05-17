import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            strategies: 'generateSW',
            outDir: 'public/build',
            manifest: {
                name: 'Schneespur',
                short_name: 'Schneespur',
                display: 'standalone',
                theme_color: '#1e293b',
                background_color: '#0f172a',
                start_url: '/driver',
                scope: '/',
                icons: [
                    {
                        src: '/pwa-icon-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/pwa-icon-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                    {
                        src: '/pwa-icon-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/pwa-icon-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
                navigateFallback: null,
                runtimeCaching: [
                    {
                        urlPattern: /\.(?:woff2?|ttf|eot|otf)$/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'fonts-cache',
                            expiration: {
                                maxEntries: 30,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                        },
                    },
                    {
                        urlPattern: /\/driver\/.*/,
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'driver-pages-cache',
                            expiration: {
                                maxEntries: 50,
                                maxAgeSeconds: 60 * 60 * 24,
                            },
                        },
                    },
                ],
            },
        }),
    ],
});
