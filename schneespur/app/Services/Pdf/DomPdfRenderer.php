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

    /**
     * Resolve the options every render path shares.
     *
     * `isRemoteEnabled` defaults to **false**: no core PDF loads anything over
     * http(s) — photos are inlined as data: URIs, the logo is an absolute file
     * path, the GPS track is an inline SVG — so leaving dompdf's fetcher on
     * only donates an SSRF surface (M018/S05). A caller that genuinely needs a
     * remote resource has to ask for it and own that decision.
     *
     * Note this gates http/https only. dompdf treats `data://` and `file://` as
     * separate allowed protocols (`Options::validateRemoteUri()`), so turning
     * this off leaves the embedded photos and the logo untouched.
     *
     * @param  array{paper?: string, orientation?: string, isRemoteEnabled?: bool}  $options
     * @return array{paper: string, orientation: string, isRemoteEnabled: bool}
     */
    public function resolveOptions(array $options): array
    {
        return [
            'paper' => $options['paper'] ?? 'a4',
            'orientation' => $options['orientation'] ?? 'portrait',
            'isRemoteEnabled' => $options['isRemoteEnabled'] ?? false,
        ];
    }

    public function render(string $view, array $data, array $options = []): string
    {
        ['paper' => $paper, 'orientation' => $orientation, 'isRemoteEnabled' => $isRemoteEnabled]
            = $this->resolveOptions($options);

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
        ['paper' => $paper, 'orientation' => $orientation, 'isRemoteEnabled' => $isRemoteEnabled]
            = $this->resolveOptions([]);

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper($paper, $orientation);
        // Set explicitly rather than inheriting the package default: a change to
        // config/dompdf.php must not be able to switch this path back on.
        $pdf->setOption('isRemoteEnabled', $isRemoteEnabled);
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
