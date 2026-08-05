<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;

class LocalStorageBackend implements StorageBackendInterface
{
    /**
     * Where bytes are written from now on. Laravel's `local` disk already points
     * at storage/app/private, which sits outside the document root in the
     * documented layout (INSTALL.de.md:73).
     */
    private const DISK = 'local';

    /** Where bytes written before M018/S04 still live. */
    private const LEGACY_DISK = 'public';

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
        Storage::disk(self::DISK)->put($relativePath, $contents);
    }

    public function retrieve(string $relativePath): ?string
    {
        if (Storage::disk(self::DISK)->exists($relativePath)) {
            return Storage::disk(self::DISK)->get($relativePath);
        }

        // Fallback-read for a half-finished migration. The registry's own
        // retrieveWithFallback() cannot help here: it falls back from the active
        // backend to 'local', and 'local' IS the active backend in this case.
        if (Storage::disk(self::LEGACY_DISK)->exists($relativePath)) {
            return Storage::disk(self::LEGACY_DISK)->get($relativePath);
        }

        return null;
    }

    public function delete(string $relativePath): bool
    {
        $private = Storage::disk(self::DISK)->delete($relativePath);
        $legacy = Storage::disk(self::LEGACY_DISK)->exists($relativePath)
            && Storage::disk(self::LEGACY_DISK)->delete($relativePath);

        return $private || $legacy;
    }

    public function exists(string $relativePath): bool
    {
        return Storage::disk(self::DISK)->exists($relativePath)
            || Storage::disk(self::LEGACY_DISK)->exists($relativePath);
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
