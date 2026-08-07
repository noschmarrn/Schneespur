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
        $path = public_path('build/sw.js');

        $this->assertFileExists($path, 'Run `npm run build` — the committed service worker is missing');

        return (string) file_get_contents($path);
    }

    public function test_module_assets_have_a_runtime_cache(): void
    {
        $this->assertStringContainsString('module-assets-cache', $this->serviceWorker());
    }

    /** The existing rules must survive the addition. */
    public function test_the_existing_caches_are_untouched(): void
    {
        $sw = $this->serviceWorker();

        $this->assertStringContainsString('fonts-cache', $sw);
        $this->assertStringContainsString('driver-pages-cache', $sw);
    }
}
