<?php

namespace Tests\Feature\Extension;

use App\Enums\LifecyclePoint;
use App\Services\Extension\LifecycleFieldRegistry;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class LifecycleFieldRegistryTest extends TestCase
{
    private function registry(): LifecycleFieldRegistry
    {
        View::addNamespace('lf-test', __DIR__ . '/_fixtures');

        $registry = new LifecycleFieldRegistry();
        $registry->registerField(LifecyclePoint::JobEnd, 'demo.field', [
            'view' => 'lf-test::lifecycle-field',
            'rules' => ['demo_field' => ['nullable', 'numeric', 'min:0']],
            'persist' => fn () => null,
            'order' => 10,
        ]);

        return $registry;
    }

    public function test_rules_and_field_keys_for_point(): void
    {
        $registry = $this->registry();

        $this->assertSame(['demo_field' => ['nullable', 'numeric', 'min:0']], $registry->rules(LifecyclePoint::JobEnd));
        $this->assertSame(['demo_field'], $registry->fieldKeys(LifecyclePoint::JobEnd));
        // A point with no contributions yields empty rules — core forms stay unchanged.
        $this->assertSame([], $registry->rules(LifecyclePoint::ShiftStart));
    }

    public function test_render_outputs_the_contribution_view(): void
    {
        $html = $this->registry()->render(LifecyclePoint::JobEnd);

        $this->assertStringContainsString('data-testid="demo-field"', $html);
        // Other points render nothing.
        $this->assertSame('', $this->registry()->render(LifecyclePoint::JobStart));
    }
}
