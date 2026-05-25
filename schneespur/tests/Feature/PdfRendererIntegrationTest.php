<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Pdf\DomPdfRenderer;
use App\Services\Pdf\PdfRendererRegistry;
use App\Services\PdfReportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Stubs\FakePdfRenderer;
use Tests\TestCase;

class PdfRendererIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PdfRendererRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }

        $this->registry = $this->app->make(PdfRendererRegistry::class);
        $this->app->instance(FakePdfRenderer::class, new FakePdfRenderer);
        $this->registry->register('fake-wkhtmltopdf', FakePdfRenderer::class);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    public function test_register_and_resolve_fake_renderer(): void
    {
        Setting::set('pdf_renderer', 'fake-wkhtmltopdf');

        $resolved = $this->registry->resolve();

        $this->assertInstanceOf(FakePdfRenderer::class, $resolved);
        $this->assertSame('fake-wkhtmltopdf', $resolved->slug());
        $this->assertSame('Fake wkhtmltopdf', $resolved->label());
    }

    public function test_default_resolves_to_dompdf_renderer(): void
    {
        $resolved = $this->registry->resolve();

        $this->assertInstanceOf(DomPdfRenderer::class, $resolved);
        $this->assertSame('dompdf', $resolved->slug());
    }

    public function test_fallback_to_dompdf_when_configured_renderer_missing(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, "'ghost-renderer' not found")
                && str_contains($msg, "falling back to 'dompdf'"));

        Setting::set('pdf_renderer', 'ghost-renderer');

        $resolved = $this->registry->resolve();

        $this->assertInstanceOf(DomPdfRenderer::class, $resolved);
    }

    public function test_pdf_report_service_uses_registry_renderer(): void
    {
        Setting::set('pdf_renderer', 'fake-wkhtmltopdf');

        $service = $this->app->make(PdfReportService::class);

        $method = new \ReflectionMethod($service, 'renderer');
        $renderer = $method->invoke($service);

        $this->assertInstanceOf(FakePdfRenderer::class, $renderer);

        $output = $renderer->render('pdf.job-report', ['job' => null], []);
        $this->assertSame('FAKE-PDF-CONTENT', $output);
    }

    public function test_available_renderers_lists_all_registered(): void
    {
        $renderers = $this->registry->availableRenderers();

        $this->assertArrayHasKey('dompdf', $renderers);
        $this->assertArrayHasKey('fake-wkhtmltopdf', $renderers);
        $this->assertSame('DomPDF', $renderers['dompdf']['label']);
        $this->assertSame('Fake wkhtmltopdf', $renderers['fake-wkhtmltopdf']['label']);
    }
}
