<?php

namespace App\Services\Report;

use App\Services\CsvExportService;

class CsvReportFormat implements ReportFormatInterface
{
    public function __construct(
        private readonly CsvExportService $csvExportService,
    ) {}

    public function slug(): string
    {
        return 'csv';
    }

    public function label(): string
    {
        return 'CSV';
    }

    public function supportedReportTypes(): array
    {
        return ['timesheet'];
    }

    public function generate(string $reportType, mixed $subject, array $params = []): string
    {
        return $this->csvExportService->buildCsv(
            $params['variant'] ?? 'all',
            $params['from'] ?? null,
            $params['to'] ?? null,
            $params['userId'] ?? null,
            $params['customerId'] ?? null,
        );
    }

    public function mimeType(): string
    {
        return 'text/csv';
    }

    public function fileExtension(): string
    {
        return 'csv';
    }
}
