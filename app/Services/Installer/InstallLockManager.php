<?php

namespace App\Services\Installer;

class InstallLockManager
{
    public function lock(): void
    {
        $dir = dirname($this->lockPath());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->lockPath(), now()->toIso8601String(), LOCK_EX);
    }

    public function isLocked(): bool
    {
        return file_exists($this->lockPath());
    }

    public function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }
}
