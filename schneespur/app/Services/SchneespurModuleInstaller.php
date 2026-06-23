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

    public function __construct(private ModuleCacheRefresher $cacheRefresher)
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

            return false;
        }

        $this->cacheRefresher->refresh();

        return true;
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
            $this->cacheRefresher->refresh();
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

        $this->cacheRefresher->refresh();

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

        $this->cacheRefresher->refresh();

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

        $prefix = $this->detectCommonPrefix($zip);

        if ($prefix === null) {
            $zip->extractTo($targetDir);
        } else {
            Log::info('schneespur-modules: stripping common prefix', [
                'slug'   => $slug,
                'prefix' => $prefix,
            ]);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if ($entry === false || $entry === '' || $entry === $prefix) {
                    continue;
                }
                if (str_starts_with($entry, '__MACOSX/')) {
                    continue;
                }

                $relative = substr($entry, strlen($prefix));
                if ($relative === '') {
                    continue;
                }

                $dest = $targetDir . '/' . $relative;

                if (str_ends_with($entry, '/')) {
                    File::ensureDirectoryExists($dest, 0755);
                    continue;
                }

                File::ensureDirectoryExists(dirname($dest), 0755);

                $contents = $zip->getFromIndex($i);
                if ($contents === false || file_put_contents($dest, $contents) === false) {
                    Log::error('schneespur-modules: extract failed', [
                        'slug'  => $slug,
                        'entry' => $entry,
                    ]);
                    $zip->close();
                    return false;
                }
            }
        }

        $zip->close();

        Log::info('schneespur-modules: unpack complete', ['slug' => $slug]);

        return true;
    }

    /**
     * Detect whether all (non-metadata) ZIP entries share one common
     * top-level folder. Returns the prefix (incl. trailing slash) when so,
     * null when the ZIP is flat or has mixed top-level entries.
     */
    private function detectCommonPrefix(ZipArchive $zip): ?string
    {
        $prefix = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false || $entry === '') {
                continue;
            }
            if (str_starts_with($entry, '__MACOSX/')) {
                continue;
            }

            $slash = strpos($entry, '/');
            if ($slash === false) {
                return null;
            }

            $top = substr($entry, 0, $slash + 1);

            if ($prefix === null) {
                $prefix = $top;
            } elseif ($prefix !== $top) {
                return null;
            }
        }

        return $prefix;
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
