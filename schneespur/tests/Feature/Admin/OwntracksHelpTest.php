<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Without "allow remote configuration" OwnTracks silently ignores the QR code,
 * so the help topic and the instruction under the QR code have to say so —
 * in every shipped language, not as a raw translation key.
 */
class OwntracksHelpTest extends TestCase
{
    use LazilyRefreshDatabase;

    public static function locales(): array
    {
        return [
            'de' => ['de', 'Allow external configuration', 'Android: Einstellungen → Remote Control', '„Bewegen“'],
            'en' => ['en', 'Allow external configuration', 'Android: Settings → Remote Control', '“Move”'],
        ];
    }

    /**
     * @dataProvider locales
     */
    public function test_help_topic_explains_remote_configuration_and_move_mode(
        string $locale,
        string $ios,
        string $android,
        string $mode,
    ): void {
        App::setLocale($locale);

        $html = view('admin.help.topics.owntracks')->render();

        $this->assertStringNotContainsString('help.owntracks_', $html);
        $this->assertStringContainsString(e($ios), $html);
        $this->assertStringContainsString(e($android), $html);
        $this->assertStringContainsString(e($mode), $html);
    }

    /**
     * @dataProvider locales
     */
    public function test_qr_instruction_names_the_remote_configuration_step(string $locale, string $ios): void
    {
        App::setLocale($locale);

        $this->assertStringContainsString($ios, __('driver.credentials_qr_instruction'));
    }
}
