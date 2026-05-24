<?php

namespace Tests\Stubs;

use App\Services\Storage\StorageBackendInterface;

class FakeS3StorageBackend implements StorageBackendInterface
{
    private array $files = [];

    public function slug(): string
    {
        return 's3';
    }

    public function label(): string
    {
        return 'Amazon S3';
    }

    public function store(string $relativePath, string $contents): void
    {
        $this->files[$relativePath] = $contents;
    }

    public function retrieve(string $relativePath): ?string
    {
        return $this->files[$relativePath] ?? null;
    }

    public function delete(string $relativePath): bool
    {
        if (isset($this->files[$relativePath])) {
            unset($this->files[$relativePath]);

            return true;
        }

        return false;
    }

    public function exists(string $relativePath): bool
    {
        return isset($this->files[$relativePath]);
    }

    public function url(string $relativePath): string
    {
        return "https://fake-s3.example.com/{$relativePath}";
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function storedFiles(): array
    {
        return $this->files;
    }
}
