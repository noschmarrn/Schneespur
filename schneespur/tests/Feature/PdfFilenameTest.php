<?php

namespace Tests\Feature;

use Tests\TestCase;

class PdfFilenameTest extends TestCase
{
    public function test_the_filename_slug_follows_the_active_language(): void
    {
        app()->setLocale('de');
        $this->assertSame('einsatznachweis', __('job.pdf_filename_proof'));

        app()->setLocale('en');
        $this->assertSame('proof-of-service', __('job.pdf_filename_proof'));
    }

    public function test_the_combined_report_slug_follows_the_active_language(): void
    {
        app()->setLocale('de');
        $this->assertSame('sammel-einsatznachweis', __('job.pdf_filename_combined'));

        app()->setLocale('en');
        $this->assertSame('combined-proof-of-service', __('job.pdf_filename_combined'));
    }
}
