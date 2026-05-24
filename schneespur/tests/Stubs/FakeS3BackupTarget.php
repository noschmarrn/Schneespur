<?php

namespace Tests\Stubs;

use App\Services\Backup\BackupTargetInterface;

class FakeS3BackupTarget implements BackupTargetInterface
{
    public function slug(): string
    {
        return 's3';
    }

    public function label(): string
    {
        return 'Amazon S3';
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
        return true;
    }
}
