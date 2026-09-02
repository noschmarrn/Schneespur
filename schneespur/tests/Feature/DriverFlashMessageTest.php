<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Driver-facing counterpart of FlashMessageTest. The driver component only
 * knows 'success' and 'error' (a 'warning' branch is missing entirely and is
 * a separate, later finding — not covered here). Errors on driver routes
 * carry the only explanation for why a shift or job action failed
 * (DriverShiftController, DriverJobController flash the exception text), so
 * they must not vanish after five seconds like a success confirmation does.
 */
class DriverFlashMessageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function renderFlash(string $type, string $text): string
    {
        session()->flash($type, $text);

        return view('components.driver-flash-message')->render();
    }

    public function test_a_success_message_still_disappears_on_its_own(): void
    {
        $html = $this->renderFlash('success', 'Schicht gestartet.');

        $this->assertStringContainsString('Schicht gestartet.', $html);
        $this->assertStringContainsString('setTimeout', $html);
    }

    public function test_an_error_stays_until_it_is_dismissed(): void
    {
        $html = $this->renderFlash('error', 'Schicht konnte nicht gestartet werden.');

        $this->assertStringContainsString('Schicht konnte nicht gestartet werden.', $html);
        $this->assertStringNotContainsString('setTimeout', $html);
    }
}
