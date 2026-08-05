<?php

namespace App\Services\Storage;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * Decides who may read a stored file, per path prefix.
 *
 * Default-deny: a prefix nobody claimed is never served. Core registers
 * 'photos/'; modules register their own prefixes in their service provider
 * (M018/S04).
 *
 * The same prefix list also drives two other things, so registering is not
 * optional for a module that stores files: StorageFallbackController refuses
 * these prefixes, and PublicMediaMigrator only moves what is listed here.
 */
class MediaAccessRegistry
{
    /** @var array<string, callable(string, ?Authenticatable): bool> */
    protected array $authorizers = [];

    public function register(string $prefix, callable $authorizer): void
    {
        $this->authorizers[$prefix] = $authorizer;
    }

    public function allows(string $relativePath, ?Authenticatable $viewer): bool
    {
        foreach ($this->authorizers as $prefix => $authorizer) {
            if (! str_starts_with($relativePath, $prefix)) {
                continue;
            }

            try {
                return (bool) $authorizer($relativePath, $viewer);
            } catch (\Throwable $e) {
                // A broken authorizer must deny, never leak.
                Log::warning('MediaAccessRegistry: authorizer failed', [
                    'prefix' => $prefix,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    public function prefixes(): array
    {
        return array_keys($this->authorizers);
    }
}
