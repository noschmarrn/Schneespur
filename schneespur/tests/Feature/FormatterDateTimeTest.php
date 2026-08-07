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
