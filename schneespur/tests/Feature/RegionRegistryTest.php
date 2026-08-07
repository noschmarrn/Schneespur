<?php

namespace Tests\Feature;

use App\Services\Format\RegionPreset;
use App\Services\Format\RegionRegistry;
use Tests\TestCase;

class RegionRegistryTest extends TestCase
{
    public function test_core_registers_the_six_shipped_regions(): void
    {
        $codes = array_keys(app(RegionRegistry::class)->labels());

        foreach (['de', 'at', 'ch', 'us', 'ca', 'gb'] as $expected) {
            $this->assertContains($expected, $codes);
        }
    }

    public function test_the_us_preset_carries_the_agreed_values(): void
    {
        $us = app(RegionRegistry::class)->get('us');

        $this->assertSame('USD', $us->currencyCode);
        $this->assertSame('before', $us->currencyPosition);
        $this->assertSame('comma_dot', $us->numberFormat);
        $this->assertSame('mdy_slash', $us->dateFormat);
        $this->assertSame('12h', $us->timeFormat);
        $this->assertSame('imperial', $us->unitDistance);
        $this->assertSame('fahrenheit', $us->unitTemperature);
        $this->assertSame('imperial', $us->unitWeight);
    }

    /** Kanada ist der Beleg dafür, dass die Vorlage allein nicht reicht. */
    public function test_canada_is_metric_but_pays_in_dollars(): void
    {
        $ca = app(RegionRegistry::class)->get('ca');

        $this->assertSame('CAD', $ca->currencyCode);
        $this->assertSame('metric', $ca->unitDistance);
        $this->assertSame('celsius', $ca->unitTemperature);
        $this->assertSame('12h', $ca->timeFormat);
    }

    /** Großbritannien fährt Meilen, wiegt aber metrisch. */
    public function test_the_uk_mixes_imperial_distance_with_metric_weight(): void
    {
        $gb = app(RegionRegistry::class)->get('gb');

        $this->assertSame('imperial', $gb->unitDistance);
        $this->assertSame('metric', $gb->unitWeight);
        $this->assertSame('celsius', $gb->unitTemperature);
        $this->assertSame('24h', $gb->timeFormat);
    }

    public function test_an_unknown_code_resolves_to_null(): void
    {
        $this->assertNull(app(RegionRegistry::class)->get('atlantis'));
    }

    public function test_a_module_can_add_its_own_region(): void
    {
        app(RegionRegistry::class)->add(new RegionPreset(
            code: 'cz',
            label: 'Česko',
            currencyCode: 'CZK',
            currencyPosition: 'after',
            numberFormat: 'dot_comma',
            dateFormat: 'dmy_dot',
            timeFormat: '24h',
            unitDistance: 'metric',
            unitTemperature: 'celsius',
            unitWeight: 'metric',
        ));

        $this->assertSame('Česko', app(RegionRegistry::class)->labels()['cz']);
    }

    public function test_to_settings_returns_every_key_the_formatter_reads(): void
    {
        $settings = app(RegionRegistry::class)->get('us')->toSettings();

        $this->assertSame([
            'format_region' => 'us',
            'currency_code' => 'USD',
            'currency_position' => 'before',
            'number_format' => 'comma_dot',
            'date_format' => 'mdy_slash',
            'time_format' => '12h',
            'unit_distance' => 'imperial',
            'unit_temperature' => 'fahrenheit',
            'unit_weight' => 'imperial',
        ], $settings);
    }
}
