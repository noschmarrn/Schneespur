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
}
