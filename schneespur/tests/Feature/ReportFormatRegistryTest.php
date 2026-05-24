<?php

namespace Tests\Feature;

use App\Services\Report\CsvReportFormat;
use App\Services\Report\PdfReportFormat;
use App\Services\Report\ReportFormatInterface;
use App\Services\Report\ReportFormatRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ReportFormatRegistryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    public function test_resolve_returns_pdf_format_by_default(): void
    {
        $registry = $this->app->make(ReportFormatRegistry::class);

        $format = $registry->resolve();

        $this->assertInstanceOf(PdfReportFormat::class, $format);
        $this->assertSame('pdf', $format->slug());
    }

    public function test_resolve_returns_csv_format(): void
    {
        $registry = $this->app->make(ReportFormatRegistry::class);

        $format = $registry->resolve('csv');

        $this->assertInstanceOf(CsvReportFormat::class, $format);
        $this->assertSame('csv', $format->slug());
    }

    public function test_available_formats_lists_pdf_and_csv(): void
    {
        $registry = $this->app->make(ReportFormatRegistry::class);

        $formats = $registry->availableFormats();

        $this->assertArrayHasKey('pdf', $formats);
        $this->assertArrayHasKey('csv', $formats);
        $this->assertSame('PDF', $formats['pdf']['label']);
        $this->assertSame('application/pdf', $formats['pdf']['mimeType']);
        $this->assertSame('pdf', $formats['pdf']['fileExtension']);
        $this->assertSame('CSV', $formats['csv']['label']);
        $this->assertSame('text/csv', $formats['csv']['mimeType']);
        $this->assertSame('csv', $formats['csv']['fileExtension']);
    }

    public function test_formats_for_type_filters_by_report_type(): void
    {
        $registry = $this->app->make(ReportFormatRegistry::class);

        $jobFormats = $registry->formatsForType('job');
        $this->assertArrayHasKey('pdf', $jobFormats);
        $this->assertArrayNotHasKey('csv', $jobFormats);

        $timesheetFormats = $registry->formatsForType('timesheet');
        $this->assertArrayHasKey('csv', $timesheetFormats);
        $this->assertArrayNotHasKey('pdf', $timesheetFormats);

        $customerFormats = $registry->formatsForType('customer');
        $this->assertArrayHasKey('pdf', $customerFormats);
        $this->assertArrayNotHasKey('csv', $customerFormats);

        $objectFormats = $registry->formatsForType('object');
        $this->assertArrayHasKey('pdf', $objectFormats);
        $this->assertArrayNotHasKey('csv', $objectFormats);
    }

    public function test_available_formats_without_filter_returns_all(): void
    {
        $registry = $this->app->make(ReportFormatRegistry::class);

        $formats = $registry->availableFormats(null);

        $this->assertCount(2, $formats);
    }

    public function test_unknown_slug_triggers_fallback_with_log_warning(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'nonexistent') && str_contains($msg, 'falling back'));

        $registry = $this->app->make(ReportFormatRegistry::class);

        $format = $registry->resolve('nonexistent');

        $this->assertInstanceOf(PdfReportFormat::class, $format);
    }

    public function test_module_can_register_custom_format(): void
    {
        $registry = $this->app->make(ReportFormatRegistry::class);

        $registry->register('salt-usage', FakeSaltUsageFormat::class);

        $formats = $registry->availableFormats();
        $this->assertArrayHasKey('salt-usage', $formats);
        $this->assertSame('Salzverbrauch', $formats['salt-usage']['label']);
        $this->assertSame('text/csv', $formats['salt-usage']['mimeType']);

        $jobFormats = $registry->formatsForType('job');
        $this->assertArrayHasKey('salt-usage', $jobFormats);
    }

    public function test_registry_is_singleton(): void
    {
        $a = $this->app->make(ReportFormatRegistry::class);
        $b = $this->app->make(ReportFormatRegistry::class);

        $this->assertSame($a, $b);
    }

    public function test_deregistered_format_triggers_log_warning_on_resolve(): void
    {
        $registry = $this->app->make(ReportFormatRegistry::class);
        $registry->register('temp', FakeSaltUsageFormat::class);
        $registry->remove('temp');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'temp') && str_contains($msg, 'falling back'));

        $format = $registry->resolve('temp');

        $this->assertInstanceOf(PdfReportFormat::class, $format);
    }

    public function test_formats_for_unknown_type_returns_empty(): void
    {
        $registry = $this->app->make(ReportFormatRegistry::class);

        $formats = $registry->formatsForType('nonexistent-type');

        $this->assertEmpty($formats);
    }
}

class FakeSaltUsageFormat implements ReportFormatInterface
{
    public function slug(): string
    {
        return 'salt-usage';
    }

    public function label(): string
    {
        return 'Salzverbrauch';
    }

    public function supportedReportTypes(): array
    {
        return ['job', 'customer'];
    }

    public function generate(string $reportType, mixed $subject, array $params = []): string
    {
        return 'salt-usage-data';
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
