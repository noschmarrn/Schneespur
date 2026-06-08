<?php

namespace Tests\Feature;

use App\Services\Extension\LocaleRegistry;
use Tests\TestCase;

class LocaleRegistryTest extends TestCase
{
    public function test_add_and_codes(): void
    {
        $reg = new LocaleRegistry;
        $reg->add('de', 'Deutsch');
        $reg->add('cs', 'Čeština');

        $this->assertSame(['de', 'cs'], $reg->codes());
        $this->assertTrue($reg->has('cs'));
        $this->assertFalse($reg->has('pl'));
    }

    public function test_labels_returns_code_to_label_map(): void
    {
        $reg = new LocaleRegistry;
        $reg->add('en', 'English');

        $this->assertSame(['en' => 'English'], $reg->labels());
    }

    public function test_core_locales_are_registered_on_the_singleton(): void
    {
        $registry = app(LocaleRegistry::class);

        $this->assertTrue($registry->has('de'));
        $this->assertTrue($registry->has('en'));
    }
}
