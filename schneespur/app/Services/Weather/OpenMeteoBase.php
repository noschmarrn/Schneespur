<?php

namespace App\Services\Weather;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class OpenMeteoBase implements WeatherProviderInterface, ArchiveWeatherProviderInterface
{
    protected const API_URL = 'https://api.open-meteo.com/v1/forecast';

    protected const ARCHIVE_URL = 'https://archive-api.open-meteo.com/v1/archive';

    protected const CURRENT_PARAMS = 'temperature_2m,precipitation,snow_depth,weather_code,wind_speed_10m,relative_humidity_2m';

    protected const TIMEOUT_SECONDS = 5;

    abstract protected function providerSlug(): string;

    abstract protected function buildQuery(float $lat, float $lon): array;

    public function fetchCurrent(float $lat, float $lon): ?WeatherData
    {
        try {
            $response = Http::timeout(static::TIMEOUT_SECONDS)
                ->get(static::API_URL, $this->buildQuery($lat, $lon));

            if ($response->failed()) {
                Log::info('Weather API returned non-success status', [
                    'provider' => $this->providerSlug(),
                    'status' => $response->status(),
                    'lat' => $lat,
                    'lon' => $lon,
                ]);

                return null;
            }

            return $this->parseResponse($response->json());
        } catch (\Throwable $e) {
            Log::info('Weather API request failed', [
                'provider' => $this->providerSlug(),
                'error' => $e->getMessage(),
                'lat' => $lat,
                'lon' => $lon,
            ]);

            return null;
        }
    }

    public function fetchAt(float $lat, float $lon, CarbonInterface $moment): ?WeatherData
    {
        // Carbon::parse statt ->copy(): WeatherData::$observed_at ist auf das
        // veränderliche Carbon typisiert, und ein Aufrufer darf uns ein
        // CarbonImmutable reichen.
        $hour = Carbon::parse($moment)->utc()->startOfHour();

        try {
            $response = Http::timeout(static::TIMEOUT_SECONDS)
                ->get(static::ARCHIVE_URL, $this->buildArchiveQuery($lat, $lon, $hour));

            if ($response->failed()) {
                Log::info('Weather archive returned non-success status', [
                    'provider' => $this->providerSlug(),
                    'status' => $response->status(),
                    'moment' => $hour->toIso8601String(),
                ]);

                return null;
            }

            return $this->parseArchiveResponse($response->json(), $hour);
        } catch (\Throwable $e) {
            Log::info('Weather archive request failed', [
                'provider' => $this->providerSlug(),
                'error' => $e->getMessage(),
                'moment' => $hour->toIso8601String(),
            ]);

            return null;
        }
    }

    protected function buildArchiveQuery(float $lat, float $lon, CarbonInterface $hour): array
    {
        return [
            'latitude' => $lat,
            'longitude' => $lon,
            'hourly' => static::CURRENT_PARAMS,
            'start_date' => $hour->format('Y-m-d'),
            'end_date' => $hour->format('Y-m-d'),
            'timezone' => 'UTC',
        ];
    }

    /**
     * Die Archiv-Antwort führt jede Größe als eigene Reihe parallel zu
     * hourly.time. Gesucht ist der Index der verlangten Stunde; fehlt er oder
     * steht dort null, gibt es für diesen Einsatz kein Wetter — und das ist
     * ein besseres Ergebnis als das Wetter von heute.
     */
    protected function parseArchiveResponse(array $data, CarbonInterface $hour): ?WeatherData
    {
        $times = $data['hourly']['time'] ?? null;

        if (! is_array($times)) {
            return null;
        }

        $index = array_search($hour->format('Y-m-d\TH:i'), $times, true);

        if ($index === false) {
            return null;
        }

        $at = fn (string $key) => $data['hourly'][$key][$index] ?? null;

        if ($at('temperature_2m') === null) {
            return null;
        }

        $code = $at('weather_code');

        return new WeatherData(
            temperature_c: (float) $at('temperature_2m'),
            precipitation_mm: (float) ($at('precipitation') ?? 0),
            snow_depth_cm: (float) ($at('snow_depth') ?? 0) * 100,
            wind_kmh: (float) ($at('wind_speed_10m') ?? 0),
            humidity_percent: (int) ($at('relative_humidity_2m') ?? 0),
            condition: ConditionMapper::fromWmoCode($code),
            weather_code: $code === null ? null : (int) $code,
            provider: $this->providerSlug(),
            observed_at: $hour,
        );
    }

    public function testConnection(float $lat, float $lon): array
    {
        $start = microtime(true);

        try {
            $response = Http::timeout(static::TIMEOUT_SECONDS)
                ->get(static::API_URL, $this->buildQuery($lat, $lon));

            $latencyMs = (int) ((microtime(true) - $start) * 1000);

            if ($response->failed()) {
                return [
                    'ok' => false,
                    'message' => __('weather.test_http_error', ['status' => $response->status()]),
                    'latency_ms' => $latencyMs,
                ];
            }

            $data = $response->json();
            if (! isset($data['current'])) {
                return [
                    'ok' => false,
                    'message' => __('weather.test_missing_data'),
                    'latency_ms' => $latencyMs,
                ];
            }

            return [
                'ok' => true,
                'message' => __('weather.test_ok'),
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            $latencyMs = (int) ((microtime(true) - $start) * 1000);

            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'latency_ms' => $latencyMs,
            ];
        }
    }

    protected function parseResponse(array $data): ?WeatherData
    {
        if (! isset($data['current'])) {
            return null;
        }

        $current = $data['current'];
        $code = $current['weather_code'] ?? null;

        return new WeatherData(
            temperature_c: (float) ($current['temperature_2m'] ?? 0),
            precipitation_mm: (float) ($current['precipitation'] ?? 0),
            // Open-Meteo liefert snow_depth in Metern — die Antwort sagt es
            // selbst in hourly_units/current_units. Gespeichert und angezeigt
            // wird die Höhe in Zentimetern.
            snow_depth_cm: (float) ($current['snow_depth'] ?? 0) * 100,
            wind_kmh: (float) ($current['wind_speed_10m'] ?? 0),
            humidity_percent: (int) ($current['relative_humidity_2m'] ?? 0),
            condition: ConditionMapper::fromWmoCode($code),
            weather_code: $code,
            provider: $this->providerSlug(),
            observed_at: isset($current['time'])
                ? Carbon::parse($current['time'])
                : Carbon::now(),
        );
    }
}
