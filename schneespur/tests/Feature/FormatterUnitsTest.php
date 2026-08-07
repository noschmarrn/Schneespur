<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Format\Formatter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FormatterUnitsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function formatter(array $settings): Formatter
    {
        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }

        app()->forgetInstance(Formatter::class);

        return app(Formatter::class);
    }

    private function metric(): Formatter
    {
        return $this->formatter([
            'number_format' => 'dot_comma',
            'unit_temperature' => 'celsius',
            'unit_distance' => 'metric',
            'unit_weight' => 'metric',
        ]);
    }

    private function imperial(): Formatter
    {
        return $this->formatter([
            'number_format' => 'comma_dot',
            'unit_temperature' => 'fahrenheit',
            'unit_distance' => 'imperial',
            'unit_weight' => 'imperial',
        ]);
    }

    public function test_temperature(): void
    {
        $this->assertSame('-4,0 °C', $this->metric()->temperature(-4.0));
        $this->assertSame('24.8 °F', $this->imperial()->temperature(-4.0));
    }

    public function test_freezing_point_converts_exactly(): void
    {
        $this->assertSame('32.0 °F', $this->imperial()->temperature(0.0));
    }

    public function test_speed_follows_the_distance_unit(): void
    {
        $this->assertSame('12,5 km/h', $this->metric()->speed(12.5));
        $this->assertSame('7.8 mph', $this->imperial()->speed(12.5));
    }

    public function test_distance_switches_scale_at_the_threshold(): void
    {
        $this->assertSame('850 m', $this->metric()->distance(850.0));
        $this->assertSame('1,2 km', $this->metric()->distance(1200.0));
    }

    public function test_imperial_distance_switches_from_feet_to_miles(): void
    {
        $this->assertSame('1,640 ft', $this->imperial()->distance(500.0));
        $this->assertSame('1.2 mi', $this->imperial()->distance(2000.0));
    }

    public function test_weight_switches_scale_at_the_threshold(): void
    {
        $this->assertSame('250 kg', $this->metric()->weight(250.0));
        $this->assertSame('1,5 t', $this->metric()->weight(1500.0));
    }

    /** Short tons (2000 lb), not the British long ton. */
    public function test_imperial_weight_uses_short_tons(): void
    {
        $this->assertSame('551 lb', $this->imperial()->weight(250.0));
        $this->assertSame('1.7 tn', $this->imperial()->weight(1500.0));
    }

    public function test_null_yields_the_placeholder_everywhere(): void
    {
        $f = $this->metric();

        $this->assertSame('—', $f->temperature(null));
        $this->assertSame('—', $f->speed(null));
        $this->assertSame('—', $f->distance(null));
        $this->assertSame('—', $f->weight(null));
    }
}
