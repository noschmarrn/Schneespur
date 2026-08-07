<?php

namespace Tests\Feature;

use App\Services\Storage\MediaAccessRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MediaAccessTest extends TestCase
{
    // The session driver is `database` and the sessions table ships inside
    // Laravel's bundled users migration — without this the web group cannot
    // even open a session.
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // /media/* lives in the web group, so RedirectToInstaller swallows it
        // until the install is finished. That is the correct production
        // behaviour — the route must not be open during installation.
        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }

        Storage::fake('local');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));

        parent::tearDown();
    }

    private function signedUrlFor(string $path): string
    {
        return URL::temporarySignedRoute('media.show', now()->addDay(), ['path' => $path]);
    }

    private function allowProbe(): void
    {
        app(MediaAccessRegistry::class)->register('probe/', fn () => true);
    }

    public function test_unregistered_prefix_is_denied(): void
    {
        Storage::disk('local')->put('probe/file.txt', 'SECRET');

        $this->get($this->signedUrlFor('probe/file.txt'))->assertStatus(403);
    }

    public function test_registered_prefix_that_allows_is_served(): void
    {
        Storage::disk('local')->put('probe/file.txt', 'SECRET');
        $this->allowProbe();

        $response = $this->get($this->signedUrlFor('probe/file.txt'));

        $response->assertStatus(200);
        $this->assertSame('SECRET', $response->getContent());
    }

    public function test_registered_prefix_that_denies_is_rejected(): void
    {
        Storage::disk('local')->put('probe/file.txt', 'SECRET');
        app(MediaAccessRegistry::class)->register('probe/', fn () => false);

        $this->get($this->signedUrlFor('probe/file.txt'))->assertStatus(403);
    }

    public function test_unsigned_request_is_rejected(): void
    {
        Storage::disk('local')->put('probe/file.txt', 'SECRET');
        $this->allowProbe();

        $this->get('/media/probe/file.txt')->assertStatus(403);
    }

    public function test_expired_signature_returns_a_readable_placeholder(): void
    {
        Storage::disk('local')->put('probe/file.txt', 'SECRET');
        $this->allowProbe();

        $expired = URL::temporarySignedRoute('media.show', now()->subMinute(), ['path' => 'probe/file.txt']);

        $response = $this->get($expired);

        $response->assertStatus(410);
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringNotContainsString('SECRET', $response->getContent());
    }

    public function test_a_forged_signature_gets_403_not_the_placeholder(): void
    {
        Storage::disk('local')->put('probe/file.txt', 'SECRET');
        $this->allowProbe();

        // The placeholder admits that something exists under this path. Only a
        // holder of a once-valid link may see it — never someone guessing
        // signatures, even with an expiry in the past.
        $forged = URL::temporarySignedRoute('media.show', now()->subMinute(), ['path' => 'probe/file.txt']);
        $forged = preg_replace('/signature=[0-9a-f]+/', 'signature=' . str_repeat('0', 64), $forged);

        $this->get($forged)->assertStatus(403);
    }

    public function test_traversal_in_the_path_is_rejected(): void
    {
        $this->allowProbe();

        $this->get($this->signedUrlFor('probe/../../.env'))->assertStatus(403);
    }

    public function test_an_svg_is_never_served_inline(): void
    {
        // finfo reads the *contents*, so an uploaded SVG would be announced as
        // image/svg+xml and rendered in our own origin — stored XSS through the
        // very route that is supposed to protect these files. Modules store
        // arbitrary file types behind this route, so upload validation alone
        // cannot be the guard.
        Storage::disk('local')->put(
            'probe/file.txt',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );
        $this->allowProbe();

        $response = $this->get($this->signedUrlFor('probe/file.txt'));

        $response->assertStatus(200);
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_a_real_image_is_still_served_inline(): void
    {
        // 1x1 GIF — the browser must still be able to render this in an <img>.
        Storage::disk('local')->put('probe/file.txt', base64_decode('R0lGODlhAQABAAAAACw='));
        $this->allowProbe();

        $response = $this->get($this->signedUrlFor('probe/file.txt'));

        $response->assertStatus(200);
        $this->assertSame('image/gif', $response->headers->get('Content-Type'));
        $this->assertNull($response->headers->get('Content-Disposition'));
    }

    public function test_served_files_carry_nosniff(): void
    {
        Storage::disk('local')->put('probe/file.txt', base64_decode('R0lGODlhAQABAAAAACw='));
        $this->allowProbe();

        $this->get($this->signedUrlFor('probe/file.txt'))
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_missing_file_is_a_404(): void
    {
        $this->allowProbe();

        $this->get($this->signedUrlFor('probe/nope.txt'))->assertStatus(404);
    }
}
