<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Storage\LocalStorageBackend;
use App\Services\Storage\StorageBackendInterface;
use App\Services\Storage\StorageBackendRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageBackendRegistryTest extends TestCase
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

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    public function test_resolve_returns_local_backend_by_default(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);

        $backend = $registry->resolve();

        $this->assertInstanceOf(LocalStorageBackend::class, $backend);
        $this->assertSame('local', $backend->slug());
    }

    public function test_available_backends_lists_local(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);

        $backends = $registry->availableBackends();

        $this->assertArrayHasKey('local', $backends);
        $this->assertTrue($backends['local']['configured']);
        $this->assertNotEmpty($backends['local']['label']);
    }

    public function test_active_slug_returns_local_by_default(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);

        $this->assertSame('local', $registry->activeSlug());
    }

    public function test_unknown_slug_triggers_fallback_with_log_warning(): void
    {
        Setting::set('storage_backend', 'nonexistent');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'nonexistent') && str_contains($msg, 'falling back'));

        $registry = $this->app->make(StorageBackendRegistry::class);

        $backend = $registry->resolve();

        $this->assertInstanceOf(LocalStorageBackend::class, $backend);
    }

    public function test_active_slug_falls_back_to_local_when_configured_backend_missing(): void
    {
        Setting::set('storage_backend', 'nonexistent');

        $registry = $this->app->make(StorageBackendRegistry::class);

        $this->assertSame('local', $registry->activeSlug());
    }

    public function test_module_can_register_custom_backend(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);

        $registry->register('test-s3', FakeS3StorageBackend::class);

        $backends = $registry->availableBackends();
        $this->assertArrayHasKey('test-s3', $backends);
        $this->assertSame('Test S3 Storage', $backends['test-s3']['label']);
        $this->assertFalse($backends['test-s3']['configured']);
    }

    public function test_store_and_retrieve_round_trip(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);
        $backend = $registry->resolve();

        $backend->store('photos/test.jpg', 'image-data');
        $contents = $backend->retrieve('photos/test.jpg');

        $this->assertSame('image-data', $contents);
    }

    public function test_retrieve_nonexistent_returns_null(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);
        $backend = $registry->resolve();

        $this->assertNull($backend->retrieve('does-not-exist.jpg'));
    }

    public function test_delete_removes_file(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);
        $backend = $registry->resolve();

        $backend->store('photos/delete-me.jpg', 'data');
        $this->assertTrue($backend->exists('photos/delete-me.jpg'));

        $deleted = $backend->delete('photos/delete-me.jpg');

        $this->assertTrue($deleted);
        $this->assertFalse($backend->exists('photos/delete-me.jpg'));
    }

    public function test_exists_returns_correct_state(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);
        $backend = $registry->resolve();

        $this->assertFalse($backend->exists('photos/check.jpg'));

        $backend->store('photos/check.jpg', 'data');

        $this->assertTrue($backend->exists('photos/check.jpg'));
    }

    public function test_url_returns_public_url(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);
        $backend = $registry->resolve();

        $backend->store('photos/url-test.jpg', 'data');

        $url = $backend->url('photos/url-test.jpg');

        $this->assertStringContainsString('photos/url-test.jpg', $url);
    }

    public function test_local_backend_is_always_configured(): void
    {
        $backend = new LocalStorageBackend;

        $this->assertTrue($backend->isConfigured());
    }

    public function test_retrieve_with_fallback_returns_from_active_backend(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);
        $backend = $registry->resolve();

        $backend->store('photos/active.jpg', 'active-data');

        $contents = $registry->retrieveWithFallback('photos/active.jpg');

        $this->assertSame('active-data', $contents);
    }

    public function test_retrieve_with_fallback_falls_back_to_local_with_log_info(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);

        $registry->register('test-s3', FakeS3StorageBackend::class);
        Setting::set('storage_backend', 'test-s3');

        $local = $registry->resolve('local');
        $local->store('photos/legacy.jpg', 'legacy-data');

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'fallback-read') && str_contains($msg, 'legacy.jpg'));

        $freshRegistry = new StorageBackendRegistry($this->app);
        $freshRegistry->register('local', LocalStorageBackend::class);
        $freshRegistry->register('test-s3', FakeS3StorageBackend::class);

        $contents = $freshRegistry->retrieveWithFallback('photos/legacy.jpg');

        $this->assertSame('legacy-data', $contents);
    }

    public function test_retrieve_with_fallback_returns_null_when_nowhere(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);

        $contents = $registry->retrieveWithFallback('photos/nowhere.jpg');

        $this->assertNull($contents);
    }

    public function test_url_with_fallback_returns_active_url_when_exists(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);
        $backend = $registry->resolve();

        $backend->store('photos/here.jpg', 'data');

        $url = $registry->urlWithFallback('photos/here.jpg');

        $this->assertStringContainsString('photos/here.jpg', $url);
    }

    public function test_url_with_fallback_falls_back_to_local_with_log_info(): void
    {
        $registry = new StorageBackendRegistry($this->app);
        $registry->register('local', LocalStorageBackend::class);
        $registry->register('test-s3', FakeS3StorageBackend::class);
        Setting::set('storage_backend', 'test-s3');

        $local = $registry->resolve('local');
        $local->store('photos/legacy-url.jpg', 'legacy-data');

        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'fallback-url') && str_contains($msg, 'legacy-url.jpg'));

        $url = $registry->urlWithFallback('photos/legacy-url.jpg');

        $this->assertStringContainsString('photos/legacy-url.jpg', $url);
    }

    public function test_resolve_with_explicit_slug(): void
    {
        $registry = $this->app->make(StorageBackendRegistry::class);

        $backend = $registry->resolve('local');

        $this->assertInstanceOf(LocalStorageBackend::class, $backend);
    }

    public function test_registry_is_singleton(): void
    {
        $a = $this->app->make(StorageBackendRegistry::class);
        $b = $this->app->make(StorageBackendRegistry::class);

        $this->assertSame($a, $b);
    }
}

class FakeS3StorageBackend implements StorageBackendInterface
{
    public function slug(): string
    {
        return 'test-s3';
    }

    public function label(): string
    {
        return 'Test S3 Storage';
    }

    public function store(string $relativePath, string $contents): void
    {
    }

    public function retrieve(string $relativePath): ?string
    {
        return null;
    }

    public function delete(string $relativePath): bool
    {
        return false;
    }

    public function exists(string $relativePath): bool
    {
        return false;
    }

    public function url(string $relativePath): string
    {
        return 'https://s3.example.com/' . $relativePath;
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
