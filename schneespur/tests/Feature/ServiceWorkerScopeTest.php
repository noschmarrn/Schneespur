<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A service worker controls only the URLs below the directory it is *served*
 * from. Registering `/build/sw.js` therefore yields the scope `/build/` and the
 * worker never controls `/driver` — `navigator.serviceWorker.controller` stays
 * null and a reload without network dies in ERR_INTERNET_DISCONNECTED. That was
 * the state on the 1.2.0 test hosting, and every offline promise the driver PWA
 * makes (including the module-assets-cache rule guarded by
 * {@see ServiceWorkerCacheTest}) was dead in practice.
 *
 * The scope is not observable from the worker's contents, which is why
 * ServiceWorkerCacheTest could not see it. It is observable from two places,
 * and both are asserted here: where the layout registers the worker, and where
 * the build puts it.
 */
class ServiceWorkerScopeTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    private function createDriver(): User
    {
        $user = User::create([
            'name' => 'Scope Driver',
            'email' => 'scope-driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Driver;
        $user->dsgvo_informed_at = now();
        $user->confirmed_version = 99;
        $user->save();

        return $user->fresh();
    }

    private function serviceWorker(): string
    {
        $path = public_path('sw.js');

        $this->assertFileExists($path, 'Run `npm run build` — the committed service worker is missing');

        return (string) file_get_contents($path);
    }

    public function test_the_driver_layout_registers_the_worker_from_the_site_root(): void
    {
        $response = $this->actingAs($this->createDriver())->get(route('driver.home'));

        $response->assertOk();
        $response->assertSee("navigator.serviceWorker.register('/sw.js')", false);
        $response->assertDontSee('/build/sw.js', false);
    }

    /**
     * The worker file itself must sit at the document root. Serving it from a
     * subdirectory would need a `Service-Worker-Allowed` response header plus
     * `mod_headers`, neither of which can be relied on across the ordinary
     * shared hosting this product has to run on.
     */
    public function test_the_worker_is_built_at_the_document_root(): void
    {
        $this->assertFileExists(
            public_path('sw.js'),
            'The service worker must be built to public/sw.js so its default scope is /'
        );

        $this->assertFileDoesNotExist(
            public_path('build/sw.js'),
            'A leftover public/build/sw.js is a second, /build/-scoped worker — remove it, do not ship both'
        );
    }

    /**
     * The Workbox runtime chunk is written next to the worker, and its name
     * carries a content hash. Vite empties public/build on every build, but
     * nothing empties the document root — so a Workbox upgrade leaves the old
     * chunk behind, committed and shipped in every release ZIP from then on.
     */
    public function test_only_one_workbox_runtime_sits_at_the_document_root(): void
    {
        $chunks = glob(public_path('workbox-*.js')) ?: [];

        $this->assertCount(
            1,
            $chunks,
            'Expected exactly one public/workbox-*.js, found: '.implode(', ', array_map('basename', $chunks))
        );
    }

    /**
     * Moving the worker out of public/build breaks every relative precache URL
     * it carries: `assets/app-x.js` used to resolve against /build/, now it
     * resolves against /. A single 404 during install aborts the whole
     * precache, so the worker would never activate at all — a far louder
     * failure than the one being fixed, and one no unit of the app can see.
     */
    public function test_precached_urls_are_absolute_into_the_build_directory(): void
    {
        $sw = $this->serviceWorker();

        $this->assertSame(
            1,
            preg_match('/precacheAndRoute\(\[(.*?)\],/s', $sw, $matches),
            'Could not find the precache manifest in sw.js'
        );

        preg_match_all('/url:"([^"]+)"/', $matches[1], $urls);

        $this->assertNotEmpty($urls[1], 'The precache manifest is empty');

        foreach ($urls[1] as $url) {
            $this->assertStringStartsWith(
                '/build/',
                $url,
                "Precached URL {$url} is not absolute: served from /sw.js it would resolve to /{$url} and 404"
            );
        }
    }

    /**
     * The layout links /manifest.webmanifest, which routes/web.php serves
     * dynamically so the name follows the brand. Vite must NOT generate a
     * second one: it would be brand-blind, and dropping it into public/ would
     * shadow the route outright, because public/.htaccess sends a request to
     * index.php only when no real file matches it.
     *
     * Without a reachable manifest the browser offers no install prompt and
     * ignores display:standalone — the driver screen stays a browser tab with
     * an address bar over it.
     */
    public function test_the_linked_web_app_manifest_is_served_and_brand_aware(): void
    {
        $response = $this->actingAs($this->createDriver())->get(route('driver.home'));

        $response->assertOk();
        $response->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);

        $manifest = $this->get('/manifest.webmanifest');

        $manifest->assertOk();
        $manifest->assertHeader('Content-Type', 'application/manifest+json');
        $this->assertSame(brand(), $manifest->json('name'));
        $this->assertSame('/', $manifest->json('scope'), 'A narrower scope would undo the worker move');

        $this->assertFileDoesNotExist(
            public_path('manifest.webmanifest'),
            'A static public/manifest.webmanifest shadows the brand-aware route — Apache serves the file first'
        );
    }
}
