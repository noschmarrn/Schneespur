<?php

namespace App\Services\Storage;

use App\Models\Setting;
use App\Services\Extension\ExtensionRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

class StorageBackendRegistry extends ExtensionRegistry
{
    public const DEFAULT_BACKEND = 'local';

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param class-string<StorageBackendInterface> $class
     */
    public function register(string $slug, mixed $class): void
    {
        parent::register($slug, $class);
    }

    public function resolve(?string $slug = null): StorageBackendInterface
    {
        $slug ??= Setting::get('storage_backend', self::DEFAULT_BACKEND);

        if (! $this->has($slug)) {
            Log::warning("StorageBackendRegistry: configured backend '{$slug}' not found, falling back to 'local'");
            $slug = self::DEFAULT_BACKEND;
        }

        return $this->container->make($this->items[$slug]);
    }

    /**
     * @return array<string, array{label: string, configured: bool}>
     */
    public function availableBackends(): array
    {
        $result = [];
        foreach ($this->all() as $slug => $class) {
            $backend = $this->container->make($class);
            $result[$slug] = [
                'label' => $backend->label(),
                'configured' => $backend->isConfigured(),
            ];
        }

        return $result;
    }

    public function activeSlug(): string
    {
        $slug = Setting::get('storage_backend', self::DEFAULT_BACKEND);

        return $this->has($slug) ? $slug : self::DEFAULT_BACKEND;
    }

    public function retrieveWithFallback(string $relativePath): ?string
    {
        $active = $this->resolve();
        $contents = $active->retrieve($relativePath);

        if ($contents !== null) {
            return $contents;
        }

        if ($active->slug() === self::DEFAULT_BACKEND) {
            return null;
        }

        $local = $this->resolve(self::DEFAULT_BACKEND);
        $localContents = $local->retrieve($relativePath);

        if ($localContents !== null) {
            Log::info("StorageBackendRegistry: fallback-read from 'local' for '{$relativePath}' (active backend: '{$active->slug()}')");
        }

        return $localContents;
    }

    public function urlWithFallback(string $relativePath): string
    {
        $active = $this->resolve();

        if ($active->exists($relativePath)) {
            return $active->url($relativePath);
        }

        if ($active->slug() === self::DEFAULT_BACKEND) {
            return $active->url($relativePath);
        }

        $local = $this->resolve(self::DEFAULT_BACKEND);

        if ($local->exists($relativePath)) {
            Log::info("StorageBackendRegistry: fallback-url from 'local' for '{$relativePath}' (active backend: '{$active->slug()}')");
            return $local->url($relativePath);
        }

        return $active->url($relativePath);
    }
}
