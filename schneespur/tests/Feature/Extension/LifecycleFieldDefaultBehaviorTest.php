<?php

namespace Tests\Feature\Extension;

use App\Enums\LifecyclePoint;
use App\Services\Extension\LifecycleFieldRegistry;
use Tests\TestCase;

class LifecycleFieldDefaultBehaviorTest extends TestCase
{
    public function test_no_contributions_yields_empty_rules_and_render(): void
    {
        $registry = app(LifecycleFieldRegistry::class);

        foreach (LifecyclePoint::cases() as $point) {
            $this->assertSame([], $registry->rules($point), "rules for {$point->value}");
            $this->assertSame([], $registry->fieldKeys($point), "fieldKeys for {$point->value}");
            $this->assertSame('', $registry->render($point), "render for {$point->value}");
        }
    }
}
