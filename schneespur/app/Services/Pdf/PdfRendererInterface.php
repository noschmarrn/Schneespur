<?php

namespace App\Services\Pdf;

interface PdfRendererInterface
{
    public function slug(): string;

    public function label(): string;

    /**
     * `isRemoteEnabled` defaults to false — a renderer must not fetch http(s)
     * resources unless the caller explicitly asks for it (M018/S05).
     *
     * @param  array{paper?: string, orientation?: string, isRemoteEnabled?: bool, footer?: array{left: string, right: string}}  $options
     * @return string Raw PDF binary
     */
    public function render(string $view, array $data, array $options = []): string;

    /**
     * @return string Raw PDF binary with footer applied
     */
    public function renderFooter(string $html, string $leftText, string $rightText): string;
}
