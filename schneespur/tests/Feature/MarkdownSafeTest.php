<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarkdownSafeTest extends TestCase
{
    public function test_javascript_links_do_not_survive(): void
    {
        $html = markdown_safe('[stored](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_data_uri_links_do_not_survive(): void
    {
        $html = markdown_safe('[x](data:text/html;base64,PHNjcmlwdD4=)');

        $this->assertStringNotContainsString('data:text/html', $html);
    }

    public function test_vbscript_links_do_not_survive(): void
    {
        $html = markdown_safe('[x](vbscript:msgbox(1))');

        $this->assertStringNotContainsString('vbscript:', $html);
    }

    public function test_safe_schemes_are_preserved(): void
    {
        $html = markdown_safe('[a](https://example.test) [b](mailto:x@example.test) [c](tel:+4912345)');

        $this->assertStringContainsString('https://example.test', $html);
        $this->assertStringContainsString('mailto:x@example.test', $html);
        $this->assertStringContainsString('tel:+4912345', $html);
    }

    public function test_relative_links_are_preserved(): void
    {
        $html = markdown_safe('[impressum](/impressum)');

        $this->assertStringContainsString('href="/impressum"', $html);
    }

    public function test_raw_html_is_still_stripped(): void
    {
        $html = markdown_safe('<script>alert(1)</script>Text');

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_ordinary_formatting_is_unchanged(): void
    {
        $html = markdown_safe("# Titel\n\nEin **fetter** Absatz.");

        $this->assertStringContainsString('<h1>Titel</h1>', $html);
        $this->assertStringContainsString('<strong>fetter</strong>', $html);
    }
}
