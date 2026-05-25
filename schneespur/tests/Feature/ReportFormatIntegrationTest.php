<?php

namespace Tests\Feature;

use App\Services\Report\CsvReportFormat;
use App\Services\Report\PdfReportFormat;
use App\Services\Report\ReportFormatRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Stubs\FakeSaltReportFormat;
use Tests\TestCase;

class ReportFormatIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ReportFormatRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }

        $this->registry = $this->app->make(ReportFormatRegistry::class);
        $this->app->instance(FakeSaltReportFormat::class, new FakeSaltReportFormat);
        $this->registry->register('salt-report', FakeSaltReportFormat::class);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    public function test_register_and_resolve_fake_format(): void
    {
        $resolved = $this->registry->resolve('salt-report');

        $this->assertInstanceOf(FakeSaltReportFormat::class, $resolved);
        $this->assertSame('salt-report', $resolved->slug());
        $this->assertSame('Salzverbrauchsbericht', $resolved->label());
        $this->assertSame('text/csv', $resolved->mimeType());
        $this->assertSame('csv', $resolved->fileExtension());
    }

    public function test_formats_for_type_filters_correctly(): void
    {
        $saltFormats = $this->registry->formatsForType('salt-usage');
        $this->assertArrayHasKey('salt-report', $saltFormats);
        $this->assertSame('Salzverbrauchsbericht', $saltFormats['salt-report']['label']);

        $jobFormats = $this->registry->formatsForType('job');
        $this->assertArrayNotHasKey('salt-report', $jobFormats);
        $this->assertArrayHasKey('pdf', $jobFormats);
    }

    public function test_available_formats_lists_core_and_module(): void
    {
        $formats = $this->registry->availableFormats();

        $this->assertArrayHasKey('pdf', $formats);
        $this->assertArrayHasKey('csv', $formats);
        $this->assertArrayHasKey('salt-report', $formats);
        $this->assertCount(3, $formats);
    }

    public function test_default_pdf_format_delegates_to_pdf_report_service(): void
    {
        $format = $this->registry->resolve('pdf');

        $this->assertInstanceOf(PdfReportFormat::class, $format);
        $this->assertSame('application/pdf', $format->mimeType());
        $this->assertSame('pdf', $format->fileExtension());
        $this->assertSame(['job', 'customer', 'object'], $format->supportedReportTypes());
    }

    public function test_fake_format_generates_expected_content(): void
    {
        $format = $this->registry->resolve('salt-report');

        $output = $format->generate('salt-usage', null);

        $this->assertStringContainsString('date,amount_kg', $output);
        $this->assertStringContainsString('120', $output);
    }
}
