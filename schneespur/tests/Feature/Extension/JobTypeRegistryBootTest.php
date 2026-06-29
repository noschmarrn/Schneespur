<?php

namespace Tests\Feature\Extension;

use App\Services\Extension\JobTypeRegistry;
use Tests\TestCase;

class JobTypeRegistryBootTest extends TestCase
{
    public function test_core_job_types_are_registered_at_boot(): void
    {
        $registry = app(JobTypeRegistry::class);

        $this->assertSame(
            ['raumen', 'streuen', 'kontrolle', 'raumen_streuen'],
            $registry->values()
        );
        $this->assertSame(__('job.type_raumen'), $registry->label('raumen'));
    }

    public function test_registry_is_singleton(): void
    {
        $this->assertSame(app(JobTypeRegistry::class), app(JobTypeRegistry::class));
    }
}
