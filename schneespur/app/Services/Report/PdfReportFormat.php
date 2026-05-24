<?php

namespace App\Services\Report;

use App\Services\PdfReportService;

class PdfReportFormat implements ReportFormatInterface
{
    public function __construct(
        private readonly PdfReportService $pdfReportService,
    ) {}

    public function slug(): string
    {
        return 'pdf';
    }

    public function label(): string
    {
        return 'PDF';
    }

    public function supportedReportTypes(): array
    {
        return ['job', 'customer', 'object'];
    }

    public function generate(string $reportType, mixed $subject, array $params = []): string
    {
        return match ($reportType) {
            'job' => $this->pdfReportService->generateJobReport($subject),
            'customer' => $this->pdfReportService->generateCustomerReport(
                $subject,
                $params['from'],
                $params['to'],
                $params['includeActive'] ?? false,
            ),
            'object' => $this->pdfReportService->generateObjectReport(
                $subject,
                $params['from'],
                $params['to'],
                $params['includeActive'] ?? false,
            ),
            default => throw new \InvalidArgumentException("PdfReportFormat does not support report type '{$reportType}'"),
        };
    }

    public function mimeType(): string
    {
        return 'application/pdf';
    }

    public function fileExtension(): string
    {
        return 'pdf';
    }
}
