<?php

namespace App\Services\Backup;

use App\Models\Setting;
use App\Services\Extension\ExtensionRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

class BackupTargetRegistry extends ExtensionRegistry
{
    public const DEFAULT_TARGET = 'local';

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param class-string<BackupTargetInterface> $class
     */
    public function register(string $slug, mixed $class): void
    {
        parent::register($slug, $class);
    }

    public function resolve(?string $slug = null): BackupTargetInterface
    {
        $slug ??= Setting::get('backup_target', self::DEFAULT_TARGET);

        if (! $this->has($slug)) {
            Log::warning("BackupTargetRegistry: configured target '{$slug}' not found, falling back to 'local'");
            $slug = self::DEFAULT_TARGET;
        }

        return $this->container->make($this->items[$slug]);
    }

    /**
     * @return array<string, array{label: string, configured: bool}>
     */
    public function availableTargets(): array
    {
        $result = [];
        foreach ($this->all() as $slug => $class) {
            $target = $this->container->make($class);
            $result[$slug] = [
                'label' => $target->label(),
                'configured' => $target->isConfigured(),
            ];
        }

        return $result;
    }

    public function activeSlug(): string
    {
        $slug = Setting::get('backup_target', self::DEFAULT_TARGET);

        return $this->has($slug) ? $slug : self::DEFAULT_TARGET;
    }
}
