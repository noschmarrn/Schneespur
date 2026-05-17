<?php

namespace App\Services\Weather;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetNorwayProvider implements WeatherProviderInterface
{
    private const API_URL = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';

    private const TIMEOUT_SECONDS = 5;

    public function fetchCurrent(float $lat, float $lon): ?WeatherData
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['User-Agent' => $this->buildUserAgent()])
                ->get(self::API_URL, [
                    'lat' => round($lat, 4),
                    'lon' => round($lon, 4),
                ]);

            if ($response->failed()) {
                Log::info('Weather API returned non-success status', [
                    'provider' => 'met_norway',
                    'status' => $response->status(),
                    'lat' => $lat,
                    'lon' => $lon,
                ]);

                return null;
            }

            $data = $response->json();

            if (! isset($data['properties']['timeseries'][0])) {
                return null;
            }

            $entry = $data['properties']['timeseries'][0];
            $details = $entry['data']['instant']['details'] ?? [];

            $symbolCode = $entry['data']['next_1_hours']['summary']['symbol_code']
                ?? $entry['data']['next_6_hours']['summary']['symbol_code']
                ?? null;

            $precipitationMm = (float) ($entry['data']['next_1_hours']['details']['precipitation_amount']
                ?? $entry['data']['next_6_hours']['details']['precipitation_amount']
                ?? 0);

            $windKmh = (float) ($details['wind_speed'] ?? 0) * 3.6;

            return new WeatherData(
                temperature_c: (float) ($details['air_temperature'] ?? 0),
                precipitation_mm: $precipitationMm,
                snowfall_mm: 0.0,
                wind_kmh: $windKmh,
                humidity_percent: (int) ($details['relative_humidity'] ?? 0),
                condition: $symbolCode
                    ? ConditionMapper::fromMetNorwaySymbol($symbolCode)
                    : 'cloudy',
                weather_code: null,
                provider: 'met_norway',
                observed_at: Carbon::parse($entry['time']),
            );
        } catch (\Throwable $e) {
            Log::info('Weather API request failed', [
                'provider' => 'met_norway',
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
                ->withHeaders(['User-Agent' => $this->buildUserAgent()])
                ->get(self::API_URL, [
                    'lat' => round($lat, 4),
                    'lon' => round($lon, 4),
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
            if (! isset($data['properties']['timeseries'])) {
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
        return 'MET Norway';
    }

    public function requiresApiKey(): bool
    {
        return false;
    }

    private function buildUserAgent(): string
    {
        $email = Setting::get('weather_user_agent_email');

        if ($email) {
            return brand_slug() . '/1.0 ' . $email;
        }

        return brand_slug() . '/1.0 (' . config('app.url') . ')';
    }
}
