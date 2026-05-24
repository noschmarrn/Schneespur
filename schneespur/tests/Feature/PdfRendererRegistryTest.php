<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Pdf\DomPdfRenderer;
use App\Services\Pdf\PdfRendererInterface;
use App\Services\Pdf\PdfRendererRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PdfRendererRegistryTest extends TestCase
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

    public function test_resolve_returns_dompdf_renderer_by_default(): void
    {
        $registry = $this->app->make(PdfRendererRegistry::class);

        $renderer = $registry->resolve();

        $this->assertInstanceOf(DomPdfRenderer::class, $renderer);
        $this->assertSame('dompdf', $renderer->slug());
    }

    public function test_available_renderers_lists_dompdf(): void
    {
        $registry = $this->app->make(PdfRendererRegistry::class);

        $renderers = $registry->availableRenderers();

        $this->assertArrayHasKey('dompdf', $renderers);
        $this->assertSame('DomPDF', $renderers['dompdf']['label']);
    }

    public function test_active_slug_returns_dompdf_by_default(): void
    {
        $registry = $this->app->make(PdfRendererRegistry::class);

        $this->assertSame('dompdf', $registry->activeSlug());
    }

    public function test_unknown_slug_triggers_fallback_with_log_warning(): void
    {
        Setting::set('pdf_renderer', 'nonexistent');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'nonexistent') && str_contains($msg, 'falling back'));

        $registry = $this->app->make(PdfRendererRegistry::class);

        $renderer = $registry->resolve();

        $this->assertInstanceOf(DomPdfRenderer::class, $renderer);
    }

    public function test_module_can_register_custom_renderer(): void
    {
        $registry = $this->app->make(PdfRendererRegistry::class);

        $registry->register('test-chrome', FakeChromePdfRenderer::class);

        $renderers = $registry->availableRenderers();
        $this->assertArrayHasKey('test-chrome', $renderers);
        $this->assertSame('Chrome PDF', $renderers['test-chrome']['label']);
    }

    public function test_registry_is_singleton(): void
    {
        $a = $this->app->make(PdfRendererRegistry::class);
        $b = $this->app->make(PdfRendererRegistry::class);

        $this->assertSame($a, $b);
    }

    public function test_active_slug_falls_back_when_configured_renderer_missing(): void
    {
        Setting::set('pdf_renderer', 'nonexistent');

        $registry = $this->app->make(PdfRendererRegistry::class);

        $this->assertSame('dompdf', $registry->activeSlug());
    }

    public function test_resolve_with_explicit_slug(): void
    {
        $registry = $this->app->make(PdfRendererRegistry::class);

        $renderer = $registry->resolve('dompdf');

        $this->assertInstanceOf(DomPdfRenderer::class, $renderer);
    }
}

class FakeChromePdfRenderer implements PdfRendererInterface
{
    public function slug(): string
    {
        return 'test-chrome';
    }

    public function label(): string
    {
        return 'Chrome PDF';
    }

    public function render(string $view, array $data, array $options = []): string
    {
        return '%PDF-fake-chrome';
    }

    public function renderFooter(string $html, string $leftText, string $rightText): string
    {
        return '%PDF-fake-chrome-footer';
    }
}
