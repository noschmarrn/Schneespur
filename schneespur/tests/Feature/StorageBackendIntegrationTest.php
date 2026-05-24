<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Storage\LocalStorageBackend;
use App\Services\Storage\StorageBackendRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Stubs\FakeS3StorageBackend;
use Tests\TestCase;

class StorageBackendIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private StorageBackendRegistry $registry;

    private FakeS3StorageBackend $fakeS3;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->registry = $this->app->make(StorageBackendRegistry::class);
        $this->fakeS3 = new FakeS3StorageBackend;

        $this->app->instance(FakeS3StorageBackend::class, $this->fakeS3);
        $this->registry->register('s3', FakeS3StorageBackend::class);
    }

    public function test_register_and_resolve_fake_backend(): void
    {
        Setting::set('storage_backend', 's3');

        $resolved = $this->registry->resolve();

        $this->assertInstanceOf(FakeS3StorageBackend::class, $resolved);
        $this->assertSame('s3', $resolved->slug());
        $this->assertSame('Amazon S3', $resolved->label());
        $this->assertTrue($resolved->isConfigured());
    }

    public function test_default_resolves_to_local_backend(): void
    {
        $resolved = $this->registry->resolve();

        $this->assertInstanceOf(LocalStorageBackend::class, $resolved);
        $this->assertSame('local', $resolved->slug());
    }

    public function test_store_via_fake_backend_receives_data(): void
    {
        Setting::set('storage_backend', 's3');

        $backend = $this->registry->resolve();
        $backend->store('photos/test-uuid.jpg', 'fake-image-content');

        $this->assertTrue($backend->exists('photos/test-uuid.jpg'));
        $this->assertSame('fake-image-content', $backend->retrieve('photos/test-uuid.jpg'));
        $this->assertSame('https://fake-s3.example.com/photos/test-uuid.jpg', $backend->url('photos/test-uuid.jpg'));
    }

    public function test_fallback_read_from_local_when_active_backend_missing_file(): void
    {
        Storage::disk('public')->put('photos/old-photo.jpg', 'local-content');

        Setting::set('storage_backend', 's3');

        $contents = $this->registry->retrieveWithFallback('photos/old-photo.jpg');

        $this->assertSame('local-content', $contents);
    }

    public function test_fallback_read_logs_info_message(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, 'fallback-read from \'local\'')
                    && str_contains($message, 'photos/old-photo.jpg');
            });

        Log::shouldReceive('warning')->zeroOrMoreTimes();

        Storage::disk('public')->put('photos/old-photo.jpg', 'local-content');
        Setting::set('storage_backend', 's3');

        $this->registry->retrieveWithFallback('photos/old-photo.jpg');
    }

    public function test_fallback_url_returns_local_url_when_active_backend_missing(): void
    {
        Storage::disk('public')->put('photos/old-photo.jpg', 'local-content');

        Setting::set('storage_backend', 's3');

        $url = $this->registry->urlWithFallback('photos/old-photo.jpg');

        $this->assertStringContainsString('photos/old-photo.jpg', $url);
        $this->assertStringNotContainsString('fake-s3', $url);
    }

    public function test_fallback_url_logs_info_message(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, 'fallback-url from \'local\'')
                    && str_contains($message, 'photos/old-photo.jpg');
            });

        Log::shouldReceive('warning')->zeroOrMoreTimes();

        Storage::disk('public')->put('photos/old-photo.jpg', 'local-content');
        Setting::set('storage_backend', 's3');

        $this->registry->urlWithFallback('photos/old-photo.jpg');
    }

    public function test_url_returns_active_backend_url_when_file_exists(): void
    {
        Setting::set('storage_backend', 's3');

        $backend = $this->registry->resolve();
        $backend->store('photos/new-photo.jpg', 'new-content');

        $url = $this->registry->urlWithFallback('photos/new-photo.jpg');

        $this->assertSame('https://fake-s3.example.com/photos/new-photo.jpg', $url);
    }

    public function test_delete_from_both_backends_via_retention_pattern(): void
    {
        Storage::disk('public')->put('photos/del-photo.jpg', 'local-content');

        Setting::set('storage_backend', 's3');

        $active = $this->registry->resolve();
        $active->store('photos/del-photo.jpg', 's3-content');

        $local = $this->registry->resolve(StorageBackendRegistry::DEFAULT_BACKEND);

        $active->delete('photos/del-photo.jpg');
        $local->delete('photos/del-photo.jpg');

        $this->assertFalse($active->exists('photos/del-photo.jpg'));
        Storage::disk('public')->assertMissing('photos/del-photo.jpg');
    }

    public function test_unknown_backend_falls_back_to_local_with_warning(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, "'nonexistent' not found")
                    && str_contains($message, "falling back to 'local'");
            });

        Setting::set('storage_backend', 'nonexistent');

        $resolved = $this->registry->resolve();

        $this->assertInstanceOf(LocalStorageBackend::class, $resolved);
    }

    public function test_available_backends_lists_all_registered(): void
    {
        $backends = $this->registry->availableBackends();

        $this->assertArrayHasKey('local', $backends);
        $this->assertArrayHasKey('s3', $backends);
        $this->assertSame('Amazon S3', $backends['s3']['label']);
        $this->assertTrue($backends['s3']['configured']);
    }

    public function test_active_slug_returns_configured_backend(): void
    {
        Setting::set('storage_backend', 's3');

        $this->assertSame('s3', $this->registry->activeSlug());
    }

    public function test_active_slug_falls_back_for_unregistered_slug(): void
    {
        Setting::set('storage_backend', 'nonexistent');

        $this->assertSame('local', $this->registry->activeSlug());
    }
}
