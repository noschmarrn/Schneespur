<?php

namespace Tests\Feature\Extension;

use App\Enums\JobType;
use App\Services\Extension\JobTypeRegistry;
use Tests\TestCase;

class JobTypeDefaultBehaviorTest extends TestCase
{
    public function test_core_types_match_the_enum_and_labels(): void
    {
        $registry = app(JobTypeRegistry::class);

        $expected = array_map(fn (JobType $c) => $c->value, JobType::cases());
        $this->assertSame($expected, $registry->values());

        foreach (JobType::cases() as $case) {
            $this->assertSame($case->label(), $registry->label($case->value));
        }
    }
}
