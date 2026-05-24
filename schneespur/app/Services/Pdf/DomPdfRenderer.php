<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;

class DomPdfRenderer implements PdfRendererInterface
{
    public function slug(): string
    {
        return 'dompdf';
    }

    public function label(): string
    {
        return 'DomPDF';
    }

    public function render(string $view, array $data, array $options = []): string
    {
        $paper = $options['paper'] ?? 'a4';
        $orientation = $options['orientation'] ?? 'portrait';
        $isRemoteEnabled = $options['isRemoteEnabled'] ?? true;

        $pdf = Pdf::loadView($view, $data);
        $pdf->setPaper($paper, $orientation);
        $pdf->setOption('isRemoteEnabled', $isRemoteEnabled);
        $pdf->render();

        if (isset($options['footer'])) {
            $this->applyCanvasFooter($pdf, $options['footer']['left'], $options['footer']['right']);
        }

        return $pdf->output();
    }

    private function applyCanvasFooter(\Barryvdh\DomPDF\PDF $pdf, string $leftText, string $rightText): void
    {
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $size = 7;
        $color = [0.58, 0.64, 0.72];
        $lineColor = [0.89, 0.91, 0.94];
        $y = $canvas->get_height() - 30;
        $xLeft = 42;
        $xRight = $canvas->get_width() - 42;

        $canvas->page_line($xLeft, $y, $xRight, $y, $lineColor, 0.5);
        $canvas->page_text($xLeft, $y + 5, $leftText, $font, $size, $color);
        $canvas->page_text(
            $xRight - $pdf->getDomPDF()->getFontMetrics()->getTextWidth($rightText, $font, $size),
            $y + 5,
            $rightText,
            $font,
            $size,
            $color,
        );
    }

    public function renderFooter(string $html, string $leftText, string $rightText): string
    {
        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $size = 7;
        $color = [0.58, 0.64, 0.72];
        $lineColor = [0.89, 0.91, 0.94];
        $y = $canvas->get_height() - 30;
        $xLeft = 42;
        $xRight = $canvas->get_width() - 42;

        $canvas->page_line($xLeft, $y, $xRight, $y, $lineColor, 0.5);
        $canvas->page_text($xLeft, $y + 5, $leftText, $font, $size, $color);
        $canvas->page_text(
            $xRight - $pdf->getDomPDF()->getFontMetrics()->getTextWidth($rightText, $font, $size),
            $y + 5,
            $rightText,
            $font,
            $size,
            $color,
        );

        return $pdf->output();
    }
}
