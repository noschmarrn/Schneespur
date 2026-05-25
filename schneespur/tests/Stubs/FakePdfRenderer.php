<?php

namespace Tests\Stubs;

use App\Services\Pdf\PdfRendererInterface;

class FakePdfRenderer implements PdfRendererInterface
{
    public function slug(): string
    {
        return 'fake-wkhtmltopdf';
    }

    public function label(): string
    {
        return 'Fake wkhtmltopdf';
    }

    public function render(string $view, array $data, array $options = []): string
    {
        return 'FAKE-PDF-CONTENT';
    }

    public function renderFooter(string $html, string $leftText, string $rightText): string
    {
        return $html . 'FAKE-FOOTER';
    }
}
