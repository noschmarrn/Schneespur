<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Backup\BackupTargetInterface;
use App\Services\Backup\BackupTargetRegistry;
use App\Services\Backup\LocalBackupTarget;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BackupTargetRegistryTest extends TestCase
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
        File::deleteDirectory(storage_path('app/backups'));
        parent::tearDown();
    }

    public function test_resolve_returns_local_backup_target_by_default(): void
    {
        $registry = $this->app->make(BackupTargetRegistry::class);

        $target = $registry->resolve();

        $this->assertInstanceOf(LocalBackupTarget::class, $target);
        $this->assertSame('local', $target->slug());
    }

    public function test_available_targets_lists_local(): void
    {
        $registry = $this->app->make(BackupTargetRegistry::class);

        $targets = $registry->availableTargets();

        $this->assertArrayHasKey('local', $targets);
        $this->assertTrue($targets['local']['configured']);
        $this->assertNotEmpty($targets['local']['label']);
    }

    public function test_active_slug_returns_local_by_default(): void
    {
        $registry = $this->app->make(BackupTargetRegistry::class);

        $this->assertSame('local', $registry->activeSlug());
    }

    public function test_unknown_slug_triggers_fallback_with_log_warning(): void
    {
        Setting::set('backup_target', 'nonexistent');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'nonexistent') && str_contains($msg, 'falling back'));

        $registry = $this->app->make(BackupTargetRegistry::class);

        $target = $registry->resolve();

        $this->assertInstanceOf(LocalBackupTarget::class, $target);
    }

    public function test_active_slug_falls_back_to_local_when_configured_target_missing(): void
    {
        Setting::set('backup_target', 'nonexistent');

        $registry = $this->app->make(BackupTargetRegistry::class);

        $this->assertSame('local', $registry->activeSlug());
    }

    public function test_module_can_register_custom_target(): void
    {
        $registry = $this->app->make(BackupTargetRegistry::class);

        $registry->register('test-s3', TestS3BackupTarget::class);

        $targets = $registry->availableTargets();
        $this->assertArrayHasKey('test-s3', $targets);
        $this->assertSame('Test S3', $targets['test-s3']['label']);
        $this->assertFalse($targets['test-s3']['configured']);
    }

    public function test_local_target_store_and_restore(): void
    {
        $sourceDir = storage_path('app/test-backup-source');
        File::ensureDirectoryExists($sourceDir);
        $sourcePath = $sourceDir . '/test-db.sqlite';
        file_put_contents($sourcePath, 'test-content');

        $target = new LocalBackupTarget;

        $stored = $target->store($sourcePath);
        $this->assertTrue($stored);

        $backups = File::files(storage_path('app/backups'));
        $this->assertCount(1, $backups);

        $backupFilename = $backups[0]->getFilename();
        $this->assertStringContainsString('test-db.sqlite', $backupFilename);

        $restoreDir = storage_path('app/test-restore');
        $restorePath = $restoreDir . '/restored.sqlite';
        $restored = $target->restore($backupFilename, $restorePath);
        $this->assertTrue($restored);
        $this->assertSame('test-content', file_get_contents($restorePath));

        File::deleteDirectory($sourceDir);
        File::deleteDirectory($restoreDir);
    }

    public function test_local_target_restore_nonexistent_returns_false(): void
    {
        $target = new LocalBackupTarget;

        $this->assertFalse($target->restore('does-not-exist.sqlite', '/tmp/out.sqlite'));
    }

    public function test_local_target_is_always_configured(): void
    {
        $target = new LocalBackupTarget;

        $this->assertTrue($target->isConfigured());
    }
}

class TestS3BackupTarget implements BackupTargetInterface
{
    public function slug(): string
    {
        return 'test-s3';
    }

    public function label(): string
    {
        return 'Test S3';
    }

    public function store(string $sourcePath): bool
    {
        return true;
    }

    public function restore(string $identifier, string $destinationPath): bool
    {
        return true;
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
