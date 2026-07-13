<?php

namespace Tests\Feature\Extension;

use App\Services\Extension\FilterRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PdfExtensionSlotTest extends TestCase
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

        View::addLocation(__DIR__ . '/../../fixtures/views');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    private function registry(): FilterRegistry
    {
        return $this->app->make(FilterRegistry::class);
    }

    public function test_directive_renders_registered_fragments(): void
    {
        $this->registry()->register('test.pdf.hook', function (array $sections, string $ctx): array {
            $sections[] = "<div>FRAGMENT_{$ctx}</div>";

            return $sections;
        });

        $html = View::make('pdf-slot-test', ['ctx' => 'ALPHA'])->render();

        $this->assertStringContainsString('FRAGMENT_ALPHA', $html);
        $this->assertStringContainsString('BEFORE', $html);
        $this->assertStringContainsString('AFTER', $html);
        // The directive must actually compile — never leak as literal text.
        $this->assertStringNotContainsString('pdfExtensionSlot', $html);
    }

    public function test_directive_orders_fragments_by_priority(): void
    {
        $this->registry()->register('test.pdf.hook', function (array $s): array {
            $s[] = 'LATE';

            return $s;
        }, 200);
        $this->registry()->register('test.pdf.hook', function (array $s): array {
            $s[] = 'EARLY';

            return $s;
        }, 50);

        $html = View::make('pdf-slot-test', ['ctx' => 'x'])->render();

        $this->assertLessThan(strpos($html, 'LATE'), strpos($html, 'EARLY'));
    }

    public function test_directive_renders_nothing_when_no_filters(): void
    {
        $html = View::make('pdf-slot-test', ['ctx' => 'x'])->render();

        // Directive compiled away to nothing (not left as literal text) and emitted no fragment.
        $this->assertStringNotContainsString('pdfExtensionSlot', $html);
        $this->assertStringContainsString('BEFORE', $html);
        $this->assertStringContainsString('AFTER', $html);
    }

    public function test_directive_survives_throwing_filter(): void
    {
        $this->registry()->register('test.pdf.hook', function (): array {
            throw new \RuntimeException('module blew up');
        });
        $this->registry()->register('test.pdf.hook', function (array $s): array {
            $s[] = 'SURVIVOR';

            return $s;
        });

        $html = View::make('pdf-slot-test', ['ctx' => 'x'])->render();

        $this->assertStringContainsString('SURVIVOR', $html);
    }

    public function test_directive_does_not_throw_on_non_array_return(): void
    {
        $this->registry()->register('test.pdf.hook', fn (): string => 'oops-not-an-array');

        $html = View::make('pdf-slot-test', ['ctx' => 'x'])->render();

        $this->assertStringContainsString('oops-not-an-array', $html);
    }
}
