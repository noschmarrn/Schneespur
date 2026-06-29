<?php

namespace Tests\Feature\Extension;

use App\Services\Extension\JobTypeRegistry;
use App\ValueObjects\JobTypeValue;
use Tests\TestCase;

class JobTypeRegistryTest extends TestCase
{
    public function test_registers_and_lists_types_in_order(): void
    {
        $registry = new JobTypeRegistry();
        $registry->registerType('streuen', 'job.type_streuen', order: 20);
        $registry->registerType('raumen', 'job.type_raumen', order: 10);

        $values = $registry->values();

        $this->assertSame(['raumen', 'streuen'], $values);
        $this->assertTrue($registry->hasType('raumen'));
        $this->assertFalse($registry->hasType('maehen'));

        $types = $registry->types();
        $this->assertContainsOnlyInstancesOf(JobTypeValue::class, $types);
        $this->assertSame('raumen', $types[0]->value);
    }

    public function test_label_resolves_translation_key_with_fallback(): void
    {
        $registry = new JobTypeRegistry();
        $registry->registerType('raumen', 'job.type_raumen');

        $this->assertSame(__('job.type_raumen'), $registry->label('raumen'));
        // Unregistered value falls back to the raw value (never throws)
        $this->assertSame('maehen', $registry->label('maehen'));
    }
}
