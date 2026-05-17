<?php

namespace App\Services\Weather;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrightSkyProvider implements WeatherProviderInterface
{
    private const API_URL = 'https://api.brightsky.dev/current_weather';

    private const TIMEOUT_SECONDS = 5;

    public function fetchCurrent(float $lat, float $lon): ?WeatherData
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->get(self::API_URL, [
                    'lat' => $lat,
                    'lon' => $lon,
                    'units' => 'dwd',
                ]);

            if ($response->failed()) {
                Log::info('Weather API returned non-success status', [
                    'provider' => 'brightsky',
                    'status' => $response->status(),
                    'lat' => $lat,
                    'lon' => $lon,
                ]);

                return null;
            }

            $data = $response->json();

            if (! isset($data['weather'])) {
                return null;
            }

            $weather = $data['weather'];

            return new WeatherData(
                temperature_c: (float) ($weather['temperature'] ?? 0),
                precipitation_mm: (float) ($weather['precipitation_60'] ?? 0),
                snowfall_mm: 0.0,
                wind_kmh: (float) ($weather['wind_speed_10'] ?? 0),
                humidity_percent: (int) ($weather['relative_humidity'] ?? 0),
                condition: self::mapCondition(
                    $weather['condition'] ?? null,
                    $weather['icon'] ?? null,
                ),
                weather_code: null,
                provider: 'brightsky',
                observed_at: isset($weather['timestamp'])
                    ? Carbon::parse($weather['timestamp'])
                    : Carbon::now(),
            );
        } catch (\Throwable $e) {
            Log::info('Weather API request failed', [
                'provider' => 'brightsky',
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
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->get(self::API_URL, [
                    'lat' => $lat,
                    'lon' => $lon,
                    'units' => 'dwd',
                ]);

            $latencyMs = (int) ((microtime(true) - $start) * 1000);

            if ($response->failed()) {
                return [
                    'ok' => false,
                    'message' => __('weather.test_http_error', ['status' => $response->status()]),
                    'latency_ms' => $latencyMs,
                ];
            }

            $data = $response->json();
            if (! isset($data['weather'])) {
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

    public function name(): string
    {
        return 'Bright Sky (DWD)';
    }

    public function requiresApiKey(): bool
    {
        return false;
    }

    public static function mapCondition(?string $condition, ?string $icon): string
    {
        return match ($condition) {
            'dry' => self::mapDryCondition($icon),
            'fog' => 'fog',
            'rain' => 'rain',
            'sleet' => 'rain',
            'snow' => 'snow',
            'hail' => 'rain-shower',
            'thunderstorm' => 'thunderstorm',
            default => 'cloudy',
        };
    }

    private static function mapDryCondition(?string $icon): string
    {
        if ($icon === null) {
            return 'cloudy';
        }

        if (str_contains($icon, 'clear')) {
            return 'clear';
        }

        if (str_contains($icon, 'partly-cloudy') || str_contains($icon, 'cloudy') || str_contains($icon, 'wind')) {
            return 'cloudy';
        }

        return 'cloudy';
    }
}
