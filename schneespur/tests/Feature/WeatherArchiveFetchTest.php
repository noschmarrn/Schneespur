<?php

namespace Tests\Feature;

use App\Services\Weather\ArchiveWeatherProviderInterface;
use App\Services\Weather\BrightSkyProvider;
use App\Services\Weather\MetNorwayProvider;
use App\Services\Weather\OpenMeteoFreeProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherArchiveFetchTest extends TestCase
{
    private function archiveResponse(): array
    {
        return [
            'hourly_units' => ['snow_depth' => 'm'],
            'hourly' => [
                'time' => ['2026-01-15T07:00', '2026-01-15T08:00', '2026-01-15T09:00'],
                'temperature_2m' => [-4.0, -3.2, -2.1],
                'precipitation' => [0.0, 0.4, 0.2],
                'snow_depth' => [0.14, 0.15, 0.15],
                'weather_code' => [3, 71, 73],
                'wind_speed_10m' => [8.0, 12.0, 14.0],
                'relative_humidity_2m' => [90, 92, 93],
            ],
        ];
    }

    public function test_the_hour_of_the_moment_is_the_hour_that_is_read(): void
    {
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->archiveResponse())]);

        $data = (new OpenMeteoFreeProvider())
            ->fetchAt(48.57, 13.46, Carbon::parse('2026-01-15 08:40:00', 'UTC'));

        $this->assertNotNull($data);
        $this->assertEqualsWithDelta(-3.2, $data->temperature_c, 0.001);
        $this->assertSame(71, $data->weather_code);
        $this->assertEqualsWithDelta(15.0, $data->snow_depth_cm, 0.001);
        $this->assertSame('2026-01-15T08:00:00+00:00', $data->observed_at->toIso8601String());
    }

    public function test_the_request_asks_for_the_day_of_the_moment_in_utc(): void
    {
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->archiveResponse())]);

        (new OpenMeteoFreeProvider())
            ->fetchAt(48.57, 13.46, Carbon::parse('2026-01-15 08:40:00', 'UTC'));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'start_date=2026-01-15')
                && str_contains($request->url(), 'end_date=2026-01-15')
                && str_contains($request->url(), 'timezone=UTC');
        });
    }

    public function test_an_hour_the_archive_does_not_carry_yields_nothing(): void
    {
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->archiveResponse())]);

        $data = (new OpenMeteoFreeProvider())
            ->fetchAt(48.57, 13.46, Carbon::parse('2026-01-15 22:00:00', 'UTC'));

        $this->assertNull($data);
    }

    public function test_a_null_value_in_the_series_yields_nothing(): void
    {
        $response = $this->archiveResponse();
        $response['hourly']['temperature_2m'][1] = null;

        Http::fake(['archive-api.open-meteo.com/*' => Http::response($response)]);

        $data = (new OpenMeteoFreeProvider())
            ->fetchAt(48.57, 13.46, Carbon::parse('2026-01-15 08:00:00', 'UTC'));

        $this->assertNull($data);
    }

    public function test_met_norway_does_not_claim_the_archive_capability(): void
    {
        $this->assertNotInstanceOf(ArchiveWeatherProviderInterface::class, new MetNorwayProvider());
        $this->assertInstanceOf(ArchiveWeatherProviderInterface::class, new OpenMeteoFreeProvider());
    }

    public function test_bright_sky_reads_the_requested_hour_from_its_history(): void
    {
        Http::fake([
            'api.brightsky.dev/weather*' => Http::response([
                'weather' => [
                    [
                        'timestamp' => '2026-01-15T08:00:00+00:00',
                        'temperature' => 0.6,
                        'precipitation' => 0.3,
                        'wind_speed' => 6.8,
                        'relative_humidity' => 98,
                        'condition' => 'snow',
                        'icon' => 'snow',
                    ],
                ],
            ]),
        ]);

        $data = (new BrightSkyProvider())
            ->fetchAt(48.57, 13.46, Carbon::parse('2026-01-15 08:20:00', 'UTC'));

        $this->assertNotNull($data);
        $this->assertEqualsWithDelta(0.6, $data->temperature_c, 0.001);
        $this->assertEqualsWithDelta(0.3, $data->precipitation_mm, 0.001);
        $this->assertSame('snow', $data->condition);
        $this->assertSame('brightsky', $data->provider);
        $this->assertSame('2026-01-15T08:00:00+00:00', $data->observed_at->toIso8601String());
    }

    public function test_bright_sky_without_a_record_yields_nothing(): void
    {
        Http::fake(['api.brightsky.dev/weather*' => Http::response(['weather' => []])]);

        $data = (new BrightSkyProvider())
            ->fetchAt(48.57, 13.46, Carbon::parse('2026-01-15 08:00:00', 'UTC'));

        $this->assertNull($data);
    }
}
