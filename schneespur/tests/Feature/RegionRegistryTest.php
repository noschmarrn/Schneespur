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

    public function test_the_german_preset_carries_the_agreed_values(): void
    {
        $de = app(RegionRegistry::class)->get('de');

        $this->assertSame('EUR', $de->currencyCode);
        $this->assertSame('after', $de->currencyPosition);
        $this->assertSame('dot_comma', $de->numberFormat);
        $this->assertSame('dmy_dot', $de->dateFormat);
        $this->assertSame('24h', $de->timeFormat);
        $this->assertSame('metric', $de->unitDistance);
        $this->assertSame('celsius', $de->unitTemperature);
        $this->assertSame('metric', $de->unitWeight);
    }

    public function test_austria_preset_matches_german_defaults(): void
    {
        $at = app(RegionRegistry::class)->get('at');

        $this->assertSame('EUR', $at->currencyCode);
        $this->assertSame('after', $at->currencyPosition);
        $this->assertSame('dot_comma', $at->numberFormat);
        $this->assertSame('dmy_dot', $at->dateFormat);
        $this->assertSame('24h', $at->timeFormat);
        $this->assertSame('metric', $at->unitDistance);
        $this->assertSame('celsius', $at->unitTemperature);
        $this->assertSame('metric', $at->unitWeight);
    }

    public function test_switzerland_uses_apostrophe_dot_and_currency_before(): void
    {
        $ch = app(RegionRegistry::class)->get('ch');

        $this->assertSame('CHF', $ch->currencyCode);
        $this->assertSame('before', $ch->currencyPosition);
        $this->assertSame('apostrophe_dot', $ch->numberFormat);
        $this->assertSame('dmy_dot', $ch->dateFormat);
        $this->assertSame('24h', $ch->timeFormat);
        $this->assertSame('metric', $ch->unitDistance);
        $this->assertSame('celsius', $ch->unitTemperature);
        $this->assertSame('metric', $ch->unitWeight);
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
            'address_format' => 'street_city_state_zip',
            'csv_delimiter' => ',',
            'week_starts_on' => '7',
        ], $settings);
    }

    public function test_the_new_regional_fields_carry_the_agreed_values(): void
    {
        $registry = app(RegionRegistry::class);

        $this->assertSame('street_zip_city', $registry->get('de')->addressFormat);
        $this->assertSame(';', $registry->get('de')->csvDelimiter);
        $this->assertSame(1, $registry->get('de')->weekStartsOn);

        $this->assertSame('street_city_state_zip', $registry->get('us')->addressFormat);
        $this->assertSame(',', $registry->get('us')->csvDelimiter);
        $this->assertSame(7, $registry->get('us')->weekStartsOn);

        $this->assertSame('street_city_state_zip', $registry->get('ca')->addressFormat);
        $this->assertSame(7, $registry->get('ca')->weekStartsOn);

        // Großbritannien schreibt "London SW1A 2AA" ohne Grafschaft, beginnt
        // die Woche aber montags — es ist nicht einfach "wie die USA".
        $this->assertSame('street_city_zip', $registry->get('gb')->addressFormat);
        $this->assertSame(',', $registry->get('gb')->csvDelimiter);
        $this->assertSame(1, $registry->get('gb')->weekStartsOn);
    }

    /**
     * Der eigentliche Zweck der Vorgabewerte: ein Sprachpaket-Modul, das heute
     * ein Preset mit den zehn alten Parametern registriert, muß nach dem Update
     * weiterlaufen. Dieser Test ist der Vertrag, nicht bloß eine Prüfung.
     */
    public function test_a_module_preset_without_the_new_fields_still_constructs(): void
    {
        $preset = new RegionPreset(
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
        );

        $this->assertSame('street_zip_city', $preset->addressFormat);
        $this->assertSame(';', $preset->csvDelimiter);
        $this->assertSame(1, $preset->weekStartsOn);
    }

    public function test_to_settings_carries_the_three_new_keys(): void
    {
        $settings = app(RegionRegistry::class)->get('us')->toSettings();

        $this->assertSame('street_city_state_zip', $settings['address_format']);
        $this->assertSame(',', $settings['csv_delimiter']);
        // Die Einstellungstabelle hält Text; der Cast gehört genau hierher.
        $this->assertSame('7', $settings['week_starts_on']);
    }
}
