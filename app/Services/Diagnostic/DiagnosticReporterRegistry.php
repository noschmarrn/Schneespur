<?php

namespace App\Services\Diagnostic;

use App\Services\Extension\ExtensionRegistry;
use Illuminate\Contracts\Container\Container;

class DiagnosticReporterRegistry extends ExtensionRegistry
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param  class-string<DiagnosticReporterInterface>  $class
     */
    public function register(string $slug, mixed $class): void
    {
        parent::register($slug, $class);
    }

    public function resolve(?string $slug = null): ?DiagnosticReporterInterface
    {
        if ($slug === null || ! $this->has($slug)) {
            return null;
        }

        return $this->container->make($this->items[$slug]);
    }

    /**
     * @return array<string, DiagnosticReporterInterface>
     */
    public function enabledReporters(): array
    {
        $enabled = [];

        foreach ($this->all() as $slug => $class) {
            try {
                $reporter = $this->container->make($class);
                if ($reporter->isEnabled()) {
                    $enabled[$slug] = $reporter;
                }
            } catch (\Throwable) {
                // Skip reporters that fail to instantiate
            }
        }

        return $enabled;
    }
}
