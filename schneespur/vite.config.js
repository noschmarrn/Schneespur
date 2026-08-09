import { resolve } from 'node:path';
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
            // The document root, NOT public/build. A service worker controls
            // only URLs below the directory it is served from, so a worker at
            // /build/sw.js gets the scope /build/ and never controls /driver —
            // the whole offline story is dead. Serving it from a subdirectory
            // would require a `Service-Worker-Allowed` response header and
            // therefore mod_headers, which ordinary shared hosting may not
            // offer. Putting the file at the root needs neither.
            outDir: 'public',
            // The layout registers the worker itself; without this the plugin
            // would emit a registerSW.js hardcoding the old /build/ scope.
            injectRegister: null,
            // The web app manifest is hand-maintained at
            // public/manifest.webmanifest, where layouts/driver.blade.php has
            // always linked it. Letting the plugin generate it puts the file in
            // public/build (it is emitted through Vite's bundle, so it ignores
            // outDir above) AND appends a bare `manifest.webmanifest` precache
            // entry after manifestTransforms has run — which, from a worker at
            // /sw.js, resolves to /manifest.webmanifest and 404s. Nothing about
            // the manifest changes per build, so a static file is the honest
            // representation anyway.
            manifest: false,
            workbox: {
                // The worker now lives one level above the assets it caches,
                // so the two paths have to be stated separately: glob the
                // build directory, then rewrite every entry to an absolute
                // URL. Without the rewrite the precache manifest would still
                // hold `assets/app-x.js`, which resolves against the worker's
                // own location — /assets/app-x.js, a 404. One 404 aborts the
                // entire precache and the worker never activates.
                globDirectory: resolve(import.meta.dirname, 'public/build'),
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff,woff2}'],
                manifestTransforms: [
                    (entries) => ({
                        manifest: entries.map((entry) => ({
                            ...entry,
                            url: `/build/${entry.url}`,
                        })),
                    }),
                ],
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
                    {
                        // Module assets ship from public/modules/{slug}/ via the
                        // @moduleAssets directive and are outside globPatterns,
                        // which is relative to outDir (public/build). Without
                        // this rule a module's driver screen is dead offline.
                        // StaleWhileRevalidate rather than NetworkFirst: the
                        // filenames carry a hash, so they are immutable, and the
                        // driver should not wait for a network attempt.
                        urlPattern: /\/modules\/.*\.(?:js|css)$/,
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'module-assets-cache',
                            expiration: {
                                maxEntries: 60,
                                maxAgeSeconds: 60 * 60 * 24 * 30,
                            },
                        },
                    },
                ],
            },
        }),
    ],
});
