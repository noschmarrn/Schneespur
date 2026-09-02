<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Format\Formatter;
use App\Support\Timezones;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DisplayTimezoneConfigTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * The installer has always written this setting; config/app.php has always
     * ignored it, reading only env(). Everything downstream —
     * Formatter::formatMoment(), Job::localStartedAt() — reads the config, so
     * the bridge is the whole fix.
     *
     * Resolution is called directly rather than by restarting the application:
     * the suite runs SQLite in :memory:, where the database lives inside the
     * connection, so refreshApplication() would throw the test data away.
     */
    public function test_a_stored_timezone_wins(): void
    {
        Setting::set('display_timezone', 'America/Chicago');

        $this->assertSame('America/Chicago', Timezones::resolve());
    }

    public function test_applying_it_reaches_the_config(): void
    {
        Setting::set('display_timezone', 'America/Chicago');

        Timezones::apply();

        $this->assertSame('America/Chicago', config('app.display_timezone'));
    }

    /**
     * The point of the bridge: the code that already converted correctly now
     * finally converts to the right zone.
     */
    public function test_the_formatter_follows_the_stored_zone(): void
    {
        Setting::set('display_timezone', 'America/Chicago');
        Timezones::apply();

        // 2026-01-15 06:30 UTC is 2026-01-15 00:30 in Chicago (UTC-6).
        $rendered = Formatter::fromSettings()
            ->time(new \DateTimeImmutable('2026-01-15 06:30:00', new \DateTimeZone('UTC')));

        $this->assertStringContainsString('00:30', $rendered);
    }

    /**
     * A fresh install has no setting yet. The env default has to survive, or
     * the installer would break on its very first request.
     */
    public function test_without_a_stored_value_the_historical_default_stands(): void
    {
        $this->assertSame('Europe/Berlin', Timezones::resolve());
    }

    /**
     * A value that is not a real IANA identifier must not be applied.
     * DateTimeZone would throw on it inside Formatter::formatMoment() — on
     * every page that shows a date, which is very nearly all of them.
     */
    public function test_a_nonsense_value_falls_through_instead_of_being_applied(): void
    {
        Setting::set('display_timezone', 'Mars/Olympus_Mons');

        $this->assertSame('Europe/Berlin', Timezones::resolve());
    }

    public function test_the_validity_check_knows_the_difference(): void
    {
        $this->assertTrue(Timezones::isValid('America/Chicago'));
        $this->assertTrue(Timezones::isValid('UTC'));
        $this->assertFalse(Timezones::isValid('Mars/Olympus_Mons'));
        $this->assertFalse(Timezones::isValid(''));
    }

    /**
     * The wiring, guarded the way the project already guards formatting drift.
     *
     * It cannot be shown through an HTTP request: the service provider boots
     * once while the test application is built, which is before any test can
     * store a setting. So the guard reads the source instead — crude, but it
     * fails if somebody removes the one line that makes all of the above
     * matter, which is exactly the regression that shipped in the first place.
     */
    public function test_the_service_provider_actually_applies_it(): void
    {
        $source = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));

        $this->assertStringContainsString(
            'Timezones::apply()',
            $source,
            'AppServiceProvider no longer applies the display timezone — the setting is inert again.'
        );
    }
}
