<?php

namespace Tests\Feature;

use App\Services\Storage\MediaAccessRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageFallbackHardeningTest extends TestCase
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

        Storage::fake('local');
        Storage::fake('public');

        // Core registers 'photos/' in AppServiceProvider::boot(); registering it
        // here keeps this test about the fallback path itself rather than about
        // who registered what.
        app(MediaAccessRegistry::class)->register('photos/', fn () => true);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));

        parent::tearDown();
    }

    public function test_the_fallback_controller_refuses_protected_prefixes(): void
    {
        Storage::disk('public')->put('photos/leak.jpg', 'SECRET');

        $response = $this->get('/storage/photos/leak.jpg');

        // The route only exists where public/storage is not a symlink
        // (routes/web.php). Either way the bytes must not come out.
        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertStringNotContainsString('SECRET', $response->getContent());
    }

    public function test_the_fallback_controller_still_serves_public_assets(): void
    {
        Storage::disk('public')->put('logo/brand.png', 'PNGDATA');

        $this->get('/storage/logo/brand.png')->assertStatus(200);
    }
}
