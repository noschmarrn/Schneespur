<?php

namespace App\Services\Backup;

interface BackupTargetInterface
{
    public function slug(): string;

    public function label(): string;

    public function store(string $sourcePath): bool;

    public function restore(string $identifier, string $destinationPath): bool;

    public function isConfigured(): bool;
}
