<?php

namespace Tests\Feature;

use App\Services\Weather\BrightSkyProvider;
use App\Services\Weather\MetNorwayProvider;
use App\Services\Weather\OpenMeteoFreeProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherSnowDepthUnitTest extends TestCase
{
    public function test_open_meteo_metres_arrive_as_centimetres(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'time' => '2026-01-15T08:00',
                    'temperature_2m' => -3.2,
                    'precipitation' => 0.4,
                    'snow_depth' => 0.15,
                    'weather_code' => 71,
                    'wind_speed_10m' => 12.0,
                    'relative_humidity_2m' => 92,
                ],
            ]),
        ]);

        $data = (new OpenMeteoFreeProvider())->fetchCurrent(48.57, 13.46);

        $this->assertNotNull($data);
        $this->assertEqualsWithDelta(15.0, $data->snow_depth_cm, 0.001);
    }

    public function test_the_array_form_carries_the_unit_in_its_key(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => ['snow_depth' => 0.07, 'weather_code' => 71],
            ]),
        ]);

        $array = (new OpenMeteoFreeProvider())->fetchCurrent(48.57, 13.46)->toArray();

        $this->assertArrayHasKey('snow_depth_cm', $array);
        $this->assertArrayNotHasKey('snowfall_mm', $array);
        $this->assertEqualsWithDelta(7.0, $array['snow_depth_cm'], 0.001);
    }

    /**
     * Bright Sky (DWD) meldet keine Schneehöhe. Fest 0.0 zu setzen hieß in
     * jedem Nachweis „kein Schnee" — eine Behauptung ohne Messung, und bei
     * einem Nachweis ist still falsch schlimmer als leer.
     */
    public function test_bright_sky_reports_an_unknown_snow_depth_not_a_zero(): void
    {
        Http::fake([
            'api.brightsky.dev/current_weather*' => Http::response([
                'weather' => [
                    'timestamp' => '2026-01-15T08:00:00+00:00',
                    'temperature' => -1.2,
                    'precipitation_60' => 0.4,
                    'wind_speed_10' => 7.0,
                    'relative_humidity' => 95,
                    'condition' => 'snow',
                    'icon' => 'snow',
                ],
            ]),
        ]);

        $data = (new BrightSkyProvider())->fetchCurrent(48.57, 13.46);

        $this->assertNotNull($data);
        $this->assertNull($data->snow_depth_cm);
    }

    public function test_bright_sky_history_reports_an_unknown_snow_depth_not_a_zero(): void
    {
        Http::fake([
            'api.brightsky.dev/weather*' => Http::response([
                'weather' => [
                    [
                        'timestamp' => '2026-01-15T08:00:00+00:00',
                        'temperature' => -1.2,
                        'condition' => 'snow',
                    ],
                ],
            ]),
        ]);

        $data = (new BrightSkyProvider())
            ->fetchAt(48.57, 13.46, \Carbon\Carbon::parse('2026-01-15 08:20:00', 'UTC'));

        $this->assertNotNull($data);
        $this->assertNull($data->snow_depth_cm);
    }

    public function test_met_norway_reports_an_unknown_snow_depth_not_a_zero(): void
    {
        Http::fake([
            'api.met.no/*' => Http::response([
                'properties' => [
                    'timeseries' => [
                        [
                            'time' => '2026-01-15T08:00:00Z',
                            'data' => [
                                'instant' => ['details' => ['air_temperature' => -1.2, 'wind_speed' => 3.0, 'relative_humidity' => 95]],
                                'next_1_hours' => ['summary' => ['symbol_code' => 'snow'], 'details' => ['precipitation_amount' => 0.4]],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $data = (new MetNorwayProvider())->fetchCurrent(48.57, 13.46);

        $this->assertNotNull($data);
        $this->assertNull($data->snow_depth_cm);
    }

    /**
     * Der zurückgestellte Task-1-Befund: läßt Open-Meteo snow_depth ganz weg,
     * gehört dort ebenfalls null hin und nicht 0 mal hundert.
     */
    public function test_a_missing_open_meteo_snow_depth_is_unknown_not_zero(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'time' => '2026-01-15T08:00',
                    'temperature_2m' => -3.2,
                    'precipitation' => 0.4,
                    'weather_code' => 71,
                ],
            ]),
        ]);

        $data = (new OpenMeteoFreeProvider())->fetchCurrent(48.57, 13.46);

        $this->assertNotNull($data);
        $this->assertNull($data->snow_depth_cm);
    }

    public function test_a_missing_snow_depth_in_the_archive_is_unknown_not_zero(): void
    {
        Http::fake([
            'archive-api.open-meteo.com/*' => Http::response([
                'hourly' => [
                    'time' => ['2026-01-15T08:00'],
                    'temperature_2m' => [-3.2],
                    'precipitation' => [0.4],
                    'weather_code' => [71],
                ],
            ]),
        ]);

        $data = (new OpenMeteoFreeProvider())
            ->fetchAt(48.57, 13.46, \Carbon\Carbon::parse('2026-01-15 08:00:00', 'UTC'));

        $this->assertNotNull($data);
        $this->assertNull($data->snow_depth_cm);
    }
}
