<?php

namespace App\Services\Pdf;

use App\Models\Setting;
use App\Services\Extension\ExtensionRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

class PdfRendererRegistry extends ExtensionRegistry
{
    public const DEFAULT_RENDERER = 'dompdf';

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param class-string<PdfRendererInterface> $class
     */
    public function register(string $slug, mixed $class): void
    {
        parent::register($slug, $class);
    }

    public function resolve(?string $slug = null): PdfRendererInterface
    {
        $slug ??= Setting::get('pdf_renderer', self::DEFAULT_RENDERER);

        if (! $this->has($slug)) {
            Log::warning("PdfRendererRegistry: configured renderer '{$slug}' not found, falling back to 'dompdf'");
            $slug = self::DEFAULT_RENDERER;
        }

        return $this->container->make($this->items[$slug]);
    }

    /**
     * @return array<string, array{label: string}>
     */
    public function availableRenderers(): array
    {
        $result = [];
        foreach ($this->all() as $slug => $class) {
            $renderer = $this->container->make($class);
            $result[$slug] = [
                'label' => $renderer->label(),
            ];
        }

        return $result;
    }

    public function activeSlug(): string
    {
        $slug = Setting::get('pdf_renderer', self::DEFAULT_RENDERER);

        return $this->has($slug) ? $slug : self::DEFAULT_RENDERER;
    }
}
