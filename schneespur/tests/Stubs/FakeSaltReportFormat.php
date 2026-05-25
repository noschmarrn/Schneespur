<?php

namespace Tests\Stubs;

use App\Services\Report\ReportFormatInterface;

class FakeSaltReportFormat implements ReportFormatInterface
{
    public function slug(): string
    {
        return 'salt-report';
    }

    public function label(): string
    {
        return 'Salzverbrauchsbericht';
    }

    public function supportedReportTypes(): array
    {
        return ['salt-usage'];
    }

    public function generate(string $reportType, mixed $subject, array $params = []): string
    {
        return "date,amount_kg\n2026-01-15,120\n2026-01-16,85";
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
