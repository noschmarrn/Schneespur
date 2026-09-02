<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Format\Formatter;
use App\Support\Timezones;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BrowserFormatSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * The running shift clock is one of the two things the server cannot
     * render. Without the zone the browser can only use the device's — right
     * by accident on a correctly set phone, wrong on one that travelled, and
     * wrong for any operation whose drivers are not in its own zone.
     */
    public function test_the_browser_is_told_which_zone_to_render_in(): void
    {
        Setting::set('display_timezone', 'America/Chicago');
        Timezones::apply();

        $settings = Formatter::fromSettings()->browserSettings();

        $this->assertArrayHasKey('timeZone', $settings);
        $this->assertSame('America/Chicago', $settings['timeZone']);
    }

    /**
     * The guard comment on browserSettings() says the list must not grow into
     * "the settings". This test is the enforcement: it grew by exactly one,
     * for a stated reason, and the next addition has to argue with a red test.
     */
    public function test_the_browser_payload_stays_at_four_fields(): void
    {
        $this->assertSame(
            ['timeFormat', 'dateFormat', 'numberFormat', 'timeZone'],
            array_keys(Formatter::fromSettings()->browserSettings())
        );
    }
}
