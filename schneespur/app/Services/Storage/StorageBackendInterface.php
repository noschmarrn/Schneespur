<?php

namespace App\Services\Storage;

interface StorageBackendInterface
{
    public function slug(): string;

    public function label(): string;

    public function store(string $relativePath, string $contents): void;

    public function retrieve(string $relativePath): ?string;

    public function delete(string $relativePath): bool;

    public function exists(string $relativePath): bool;

    public function url(string $relativePath): string;

    public function isConfigured(): bool;
}
