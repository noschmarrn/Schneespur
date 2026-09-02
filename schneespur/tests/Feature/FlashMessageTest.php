<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FlashMessageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function renderFlash(string $type, string $text): string
    {
        session()->flash($type, $text);

        return view('components.flash-message')->render();
    }

    public function test_a_success_message_still_disappears_on_its_own(): void
    {
        $html = $this->renderFlash('success', 'Gespeichert.');

        $this->assertStringContainsString('Gespeichert.', $html);
        $this->assertStringContainsString('setTimeout', $html);
    }

    /**
     * A warning asks the operator to do something. It has to survive long
     * enough to be read — the geocoding failure under Company & Location is
     * reported this way, and the US test report describes it as "nothing
     * happens, no hint".
     */
    public function test_a_warning_stays_until_it_is_dismissed(): void
    {
        $html = $this->renderFlash('warning', 'Adresse nicht aufgelöst.');

        $this->assertStringContainsString('Adresse nicht aufgelöst.', $html);
        $this->assertStringNotContainsString('setTimeout', $html);
    }

    public function test_an_error_stays_until_it_is_dismissed(): void
    {
        $html = $this->renderFlash('error', 'Speichern fehlgeschlagen.');

        $this->assertStringContainsString('Speichern fehlgeschlagen.', $html);
        $this->assertStringNotContainsString('setTimeout', $html);
    }
}
