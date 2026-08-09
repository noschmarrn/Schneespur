<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The service worker is a build artifact, and this repository commits it —
 * shared hosting has no CLI to run a build. A rule that silently disappears
 * on the next Vite upgrade would not fail anything until a driver stands in
 * a field with a dead module screen, so it gets asserted here.
 */
class ServiceWorkerCacheTest extends TestCase
{
    private function serviceWorker(): string
    {
        // The document root, not public/build — see ServiceWorkerScopeTest for
        // why the worker has to be served from there.
        $path = public_path('sw.js');

        $this->assertFileExists($path, 'Run `npm run build` — the committed service worker is missing');

        return (string) file_get_contents($path);
    }

    public function test_module_assets_have_a_runtime_cache(): void
    {
        $sw = $this->serviceWorker();

        // The build is minified (see the note above), so this pins the
        // assertion to the exact shape of the module-assets-cache rule
        // rather than to independent substrings. A bare "does the string
        // module-assets-cache occur" check would still pass if a later
        // edit kept the cache name but reverted the handler to
        // NetworkFirst — the very regression this rule exists to
        // prevent, per the comment in vite.config.js — or widened the
        // expiration. Workbox-build turns the `expiration` option into
        // an ExpirationPlugin, e.g.:
        // new e.StaleWhileRevalidate({cacheName:"module-assets-cache",
        // plugins:[new e.ExpirationPlugin({maxEntries:60,
        // maxAgeSeconds:2592e3})]})
        $found = preg_match(
            '/new \w+\.(\w+)\(\{cacheName:"module-assets-cache",plugins:\[new \w+\.ExpirationPlugin\(\{maxEntries:(\d+),maxAgeSeconds:([0-9.e+-]+)\}\)\]\}\)/',
            $sw,
            $matches
        );

        $this->assertSame(1, $found, 'Could not find the module-assets-cache rule in the expected shape in sw.js');

        [, $handler, $maxEntries, $maxAgeSeconds] = $matches;

        $this->assertSame(
            'StaleWhileRevalidate',
            $handler,
            'module-assets-cache handler regressed: module asset filenames are hashed and immutable, so StaleWhileRevalidate is required — NetworkFirst would make the driver wait on a network round trip'
        );
        $this->assertSame(60, (int) $maxEntries, 'module-assets-cache maxEntries regressed');
        $this->assertSame(60 * 60 * 24 * 30, (int) (float) $maxAgeSeconds, 'module-assets-cache maxAgeSeconds regressed');
    }

    /** The existing rules must survive the addition. */
    public function test_the_existing_caches_are_untouched(): void
    {
        $sw = $this->serviceWorker();

        $this->assertStringContainsString('fonts-cache', $sw);
        $this->assertStringContainsString('driver-pages-cache', $sw);
    }
}
