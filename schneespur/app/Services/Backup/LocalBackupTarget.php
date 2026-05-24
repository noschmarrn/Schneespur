<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;

class LocalBackupTarget implements BackupTargetInterface
{
    public function slug(): string
    {
        return 'local';
    }

    public function label(): string
    {
        return __('backup.target_local');
    }

    public function store(string $sourcePath): bool
    {
        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $filename = now()->format('Y-m-d_His') . '_' . basename($sourcePath);
        $destination = $backupDir . '/' . $filename;

        return File::copy($sourcePath, $destination);
    }

    public function restore(string $identifier, string $destinationPath): bool
    {
        $sourcePath = storage_path('app/backups/' . $identifier);

        if (! File::exists($sourcePath)) {
            return false;
        }

        File::ensureDirectoryExists(dirname($destinationPath));

        return File::copy($sourcePath, $destinationPath);
    }

    public function isConfigured(): bool
    {
        return true;
    }
}
