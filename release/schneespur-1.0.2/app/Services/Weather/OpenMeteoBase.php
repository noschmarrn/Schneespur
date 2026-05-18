<?php

namespace App\Services\Weather;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class OpenMeteoBase implements WeatherProviderInterface
{
    protected const API_URL = 'https://api.open-meteo.com/v1/forecast';

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
            snowfall_mm: (float) ($current['snow_depth'] ?? 0),
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
