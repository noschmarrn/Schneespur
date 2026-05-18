<?php

namespace App\Services;

use App\Services\Diagnostic\DiagnosticManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZipArchive;

class SchneespurModuleInstaller
{
    private string $modulesPath;

    public function __construct()
    {
        $this->modulesPath = rtrim(config('schneespur_modules.modules_path'), '/');
    }

    public function install(string $zipPath, string $slug): bool
    {
        $this->assertValidSlug($slug);
        Log::info('schneespur-modules: install started', ['slug' => $slug]);

        $targetDir = $this->modulePath($slug);

        if (File::isDirectory($targetDir)) {
            Log::warning('schneespur-modules: module directory already exists', ['slug' => $slug]);
            return false;
        }

        $result = $this->extractZip($zipPath, $targetDir, $slug);

        if (! $result) {
            $this->reportDiagnostic('module_install_failed', $slug, 'ZIP extraction failed');
        }

        return $result;
    }

    public function update(string $zipPath, string $slug): bool
    {
        $this->assertValidSlug($slug);
        Log::info('schneespur-modules: update started', ['slug' => $slug]);

        $targetDir = $this->modulePath($slug);
        $backupDir = $this->backupPath($slug);

        if (File::isDirectory($backupDir)) {
            File::deleteDirectory($backupDir);
        }

        if (File::isDirectory($targetDir)) {
            File::moveDirectory($targetDir, $backupDir);
        }

        if ($this->extractZip($zipPath, $targetDir, $slug)) {
            if (File::isDirectory($backupDir)) {
                File::deleteDirectory($backupDir);
            }
            Log::info('schneespur-modules: update complete', ['slug' => $slug]);
            return true;
        }

        Log::error('schneespur-modules: update failed, triggering rollback', ['slug' => $slug]);
        $this->reportDiagnostic('module_update_failed', $slug, 'ZIP extraction failed, rollback triggered');
        $this->rollback($slug);
        return false;
    }

    public function remove(string $slug): bool
    {
        $this->assertValidSlug($slug);
        $targetDir = $this->modulePath($slug);

        if (! File::isDirectory($targetDir)) {
            Log::warning('schneespur-modules: remove failed — directory not found', ['slug' => $slug]);
            return false;
        }

        File::deleteDirectory($targetDir);
        Log::info('schneespur-modules: module removed', ['slug' => $slug]);

        return true;
    }

    public function rollback(string $slug): bool
    {
        $this->assertValidSlug($slug);
        $targetDir = $this->modulePath($slug);
        $backupDir = $this->backupPath($slug);

        if (! File::isDirectory($backupDir)) {
            Log::error('schneespur-modules: rollback failed — no backup found', ['slug' => $slug]);
            return false;
        }

        if (File::isDirectory($targetDir)) {
            File::deleteDirectory($targetDir);
        }

        File::moveDirectory($backupDir, $targetDir);
        Log::info('schneespur-modules: rollback triggered', ['slug' => $slug]);

        return true;
    }

    private function extractZip(string $zipPath, string $targetDir, string $slug): bool
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            Log::error('schneespur-modules: ZIP open failed', [
                'slug'  => $slug,
                'error' => $result,
            ]);
            return false;
        }

        if (! $this->validateZipEntries($zip, $slug)) {
            $zip->close();
            return false;
        }

        File::ensureDirectoryExists($targetDir, 0755);
        $zip->extractTo($targetDir);
        $zip->close();

        Log::info('schneespur-modules: unpack complete', ['slug' => $slug]);

        return true;
    }

    private function validateZipEntries(ZipArchive $zip, string $slug): bool
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            if ($entry === false) {
                continue;
            }

            if (str_contains($entry, '..') || str_starts_with($entry, '/')) {
                Log::error('schneespur-modules: path traversal detected in ZIP', [
                    'slug'  => $slug,
                    'entry' => $entry,
                ]);
                return false;
            }
        }

        return true;
    }

    private function assertValidSlug(string $slug): void
    {
        if (! preg_match('/^[a-z0-9_-]+$/i', $slug)) {
            throw new RuntimeException("Ungültiger Modul-Slug: {$slug}");
        }
    }

    private function modulePath(string $slug): string
    {
        return $this->modulesPath . '/' . $slug;
    }

    private function backupPath(string $slug): string
    {
        return $this->modulesPath . '/' . $slug . '.bak';
    }

    private function reportDiagnostic(string $type, string $slug, string $reason): void
    {
        try {
            app(DiagnosticManager::class)->report($type, [
                'module_slug' => $slug,
                'reason' => $reason,
            ]);
        } catch (\Throwable) {
        }
    }
}
