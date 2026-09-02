<?php

namespace Tests\Feature;

use App\Models\WeatherSnapshot;
use Tests\TestCase;

class WeatherCodeDisplayTest extends TestCase
{
    public function test_a_wmo_code_maps_to_a_translated_label(): void
    {
        app()->setLocale('de');

        $snapshot = new WeatherSnapshot(['weather_code' => 1]);

        $label = $snapshot->weatherLabel();

        $this->assertNotSame('1', $label);
        $this->assertNotSame('weather.condition_clear', $label);
        $this->assertNotEmpty($label);
    }

    public function test_a_missing_code_produces_a_recognisable_unknown_label_not_cloudy(): void
    {
        app()->setLocale('de');

        $snapshot = new WeatherSnapshot(['weather_code' => null]);

        $label = $snapshot->weatherLabel();

        $this->assertNotEmpty($label);
        $this->assertSame(__('weather.wmo_unknown', ['code' => '–']), $label);
        $this->assertNotSame(__('weather.condition_cloudy'), $label);
    }

    public function test_light_moderate_and_heavy_snowfall_produce_distinct_labels(): void
    {
        app()->setLocale('de');

        $light = (new WeatherSnapshot(['weather_code' => 71]))->weatherLabel();
        $moderate = (new WeatherSnapshot(['weather_code' => 73]))->weatherLabel();
        $heavy = (new WeatherSnapshot(['weather_code' => 75]))->weatherLabel();

        $this->assertNotSame($light, $moderate);
        $this->assertNotSame($moderate, $heavy);
        $this->assertNotSame($light, $heavy);
    }
}
