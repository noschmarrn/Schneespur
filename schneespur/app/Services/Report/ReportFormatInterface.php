<?php

namespace App\Services\Report;

interface ReportFormatInterface
{
    public function slug(): string;

    public function label(): string;

    /**
     * @return string[] Report type slugs this format can export (e.g. ['job', 'customer', 'object'])
     */
    public function supportedReportTypes(): array;

    /**
     * @return string File content as string
     */
    public function generate(string $reportType, mixed $subject, array $params = []): string;

    public function mimeType(): string;

    public function fileExtension(): string;
}
