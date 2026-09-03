<?php

namespace Tests\Feature;

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
}
