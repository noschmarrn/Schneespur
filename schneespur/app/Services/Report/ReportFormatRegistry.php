<?php

namespace App\Services\Report;

use App\Services\Extension\ExtensionRegistry;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

class ReportFormatRegistry extends ExtensionRegistry
{
    public const DEFAULT_FORMAT = 'pdf';

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param class-string<ReportFormatInterface> $class
     */
    public function register(string $slug, mixed $class): void
    {
        parent::register($slug, $class);
    }

    public function resolve(?string $slug = null): ReportFormatInterface
    {
        $slug ??= self::DEFAULT_FORMAT;

        if (! $this->has($slug)) {
            Log::warning("ReportFormatRegistry: requested format '{$slug}' not found, falling back to 'pdf'");
            $slug = self::DEFAULT_FORMAT;
        }

        return $this->container->make($this->items[$slug]);
    }

    /**
     * @return array<string, array{label: string, mimeType: string, fileExtension: string}>
     */
    public function availableFormats(?string $reportType = null): array
    {
        $result = [];

        foreach ($this->all() as $slug => $class) {
            $format = $this->container->make($class);

            if ($reportType !== null && ! in_array($reportType, $format->supportedReportTypes(), true)) {
                continue;
            }

            $result[$slug] = [
                'label' => $format->label(),
                'mimeType' => $format->mimeType(),
                'fileExtension' => $format->fileExtension(),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, array{label: string, mimeType: string, fileExtension: string}>
     */
    public function formatsForType(string $reportType): array
    {
        return $this->availableFormats($reportType);
    }
}
