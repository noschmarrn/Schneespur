<?php

namespace Tests\Feature\Extension;

use App\Enums\LifecyclePoint;
use App\Services\Extension\LifecycleFieldRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class LifecycleFieldDirectiveTest extends TestCase
{
    public function test_directive_renders_registered_contribution(): void
    {
        View::addNamespace('lf-test', dirname(__DIR__) . '/Extension/_fixtures');

        app(LifecycleFieldRegistry::class)->registerField(LifecyclePoint::JobEnd, 'demo.field', [
            'view' => 'lf-test::lifecycle-field',
        ]);

        $compiled = Blade::compileString("@lifecycleFields('job.end')");
        // eval() is safe here: $compiled is the output of Blade::compileString() applied
        // to a literal template string defined in this test — no user input is involved.
        // This is the standard Laravel pattern for unit-testing Blade directives.
        // ob_start/ob_get_clean captures the echo output that eval() does not return.
        ob_start();
        eval('?>' . $compiled);
        $html = ob_get_clean();

        $this->assertStringContainsString('data-testid="demo-field"', $html);
    }

    public function test_registry_is_singleton(): void
    {
        $this->assertSame(app(LifecycleFieldRegistry::class), app(LifecycleFieldRegistry::class));
    }
}
