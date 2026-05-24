<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;

class LocalStorageBackend implements StorageBackendInterface
{
    public function slug(): string
    {
        return 'local';
    }

    public function label(): string
    {
        return __('storage.backend_local');
    }

    public function store(string $relativePath, string $contents): void
    {
        Storage::disk('public')->put($relativePath, $contents);
    }

    public function retrieve(string $relativePath): ?string
    {
        if (! Storage::disk('public')->exists($relativePath)) {
            return null;
        }

        return Storage::disk('public')->get($relativePath);
    }

    public function delete(string $relativePath): bool
    {
        return Storage::disk('public')->delete($relativePath);
    }

    public function exists(string $relativePath): bool
    {
        return Storage::disk('public')->exists($relativePath);
    }

    public function url(string $relativePath): string
    {
        return Storage::disk('public')->url($relativePath);
    }

    public function isConfigured(): bool
    {
        return true;
    }
}
