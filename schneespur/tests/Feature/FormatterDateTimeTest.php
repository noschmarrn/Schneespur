<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Format\Formatter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FormatterDateTimeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * The Formatter is a singleton that reads its settings once. A test that
     * changes settings must therefore drop the cached instance, or it keeps
     * formatting with the values from the previous test.
     */
    private function formatter(array $settings): Formatter
    {
        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }

        app()->forgetInstance(Formatter::class);

        return app(Formatter::class);
    }

    public function test_german_date_and_time(): void
    {
        $f = $this->formatter(['date_format' => 'dmy_dot', 'time_format' => '24h']);
        $moment = new \DateTimeImmutable('2026-08-06 12:30:00', new \DateTimeZone('UTC'));

        $this->assertSame('06.08.2026', $f->date($moment));
        $this->assertSame('14:30', $f->time($moment));
        $this->assertSame('06.08.2026 14:30', $f->dateTime($moment));
    }

    public function test_us_date_and_time(): void
    {
        $f = $this->formatter(['date_format' => 'mdy_slash', 'time_format' => '12h']);
        $moment = new \DateTimeImmutable('2026-08-06 12:30:00', new \DateTimeZone('UTC'));

        $this->assertSame('08/06/2026', $f->date($moment));
        $this->assertSame('2:30 PM', $f->time($moment));
    }

    /**
     * The GPS trace in an Einsatznachweis is evidence, so it keeps its
     * seconds — but it must not be the one column in the document that
     * ignores the time setting.
     */
    public function test_seconds_are_available_and_follow_the_time_setting(): void
    {
        $moment = new \DateTimeImmutable('2026-08-06 12:30:15', new \DateTimeZone('UTC'));

        $german = $this->formatter(['time_format' => '24h']);
        $this->assertSame('14:30:15', $german->time($moment, true));
        $this->assertSame('14:30', $german->time($moment));

        $american = $this->formatter(['time_format' => '12h']);
        $this->assertSame('2:30:15 PM', $american->time($moment, true));
    }

    /** Audit trails and DSGVO confirmations want the whole stamp with seconds. */
    public function test_date_time_carries_the_seconds_flag_through(): void
    {
        $moment = new \DateTimeImmutable('2026-08-06 12:30:15', new \DateTimeZone('UTC'));

        $f = $this->formatter(['date_format' => 'mdy_slash', 'time_format' => '12h']);

        $this->assertSame('08/06/2026 2:30:15 PM', $f->dateTime($moment, true));
        $this->assertSame('08/06/2026 2:30 PM', $f->dateTime($moment));
        $this->assertSame('—', $f->dateTime(null, true));
    }

    /**
     * The layout hands three values to the browser. They come from the
     * singleton, not from three fresh Setting::get() queries per page load,
     * and they carry the Formatter's own defaults so the two sides cannot
     * drift apart.
     */
    public function test_the_browser_gets_exactly_the_three_values_it_needs(): void
    {
        $f = $this->formatter([
            'time_format' => '12h',
            'date_format' => 'mdy_slash',
            'number_format' => 'comma_dot',
        ]);

        $this->assertSame([
            'timeFormat' => '12h',
            'dateFormat' => 'mdy_slash',
            'numberFormat' => 'comma_dot',
        ], $f->browserSettings());
    }

    public function test_seconds_still_yield_the_placeholder_for_null(): void
    {
        $this->assertSame('—', $this->formatter([])->time(null, true));
        $this->assertSame('—', format_time(null, true));
    }

    /**
     * The display timezone is applied by the Formatter, not by the caller.
     * ~35 call sites do it by hand today and some forget.
     */
    public function test_the_display_timezone_is_applied(): void
    {
        config(['app.display_timezone' => 'America/Toronto']);

        $f = $this->formatter(['date_format' => 'mdy_slash', 'time_format' => '24h']);
        $moment = new \DateTimeImmutable('2026-08-06 01:30:00', new \DateTimeZone('UTC'));

        // 01:30 UTC is the previous evening in Toronto.
        $this->assertSame('08/05/2026', $f->date($moment));
        $this->assertSame('21:30', $f->time($moment));
    }

    public function test_null_yields_the_placeholder(): void
    {
        $f = $this->formatter([]);

        $this->assertSame('—', $f->date(null));
        $this->assertSame('—', $f->time(null));
        $this->assertSame('—', $f->dateTime(null));
    }

    public function test_the_helpers_go_through_the_service(): void
    {
        $this->formatter(['date_format' => 'iso']);

        $this->assertSame('2026-08-06', format_date(new \DateTimeImmutable('2026-08-06 09:00', new \DateTimeZone('UTC'))));
    }
}
