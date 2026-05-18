<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Weather\ConditionMapper;
use App\Services\Weather\WeatherProviderRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ForecastService
{
    private const DEFAULT_CACHE_TTL = 300;

    public function __construct(
        private readonly WeatherProviderRegistry $registry,
    ) {}

    public function current(float $lat, float $lon): ?array
    {
        $providerSlug = $this->registry->activeSlug();
        $cacheKey = "weather_forecast_{$providerSlug}";
        $cacheTtl = (int) Setting::get('weather_cache_ttl', self::DEFAULT_CACHE_TTL);

        try {
            return Cache::remember($cacheKey, $cacheTtl, function () use ($lat, $lon) {
                return $this->fetchViaProvider($lat, $lon);
            });
        } catch (\Throwable $e) {
            Log::warning('ForecastService: cache layer failed, attempting direct fetch', [
                'error' => $e->getMessage(),
                'lat' => $lat,
                'lon' => $lon,
            ]);

            return $this->fetchViaProvider($lat, $lon);
        }
    }

    private function fetchViaProvider(float $lat, float $lon): ?array
    {
        $provider = $this->registry->resolve();
        $data = $provider->fetchCurrent($lat, $lon);

        if ($data === null) {
            return null;
        }

        return [
            'temperature' => $data->temperature_c,
            'precipitation' => $data->precipitation_mm,
            'snow_depth' => $data->snowfall_mm,
            'weather_code' => $data->weather_code ?? 0,
            'wind_speed' => $data->wind_kmh,
            'humidity' => $data->humidity_percent,
            'icon' => ConditionMapper::icon($data->condition),
            'label' => __('weather.wmo_' . ($data->weather_code ?? 0)),
            'time' => $data->observed_at->format('Y-m-d\TH:i'),
            'provider' => $data->provider,
        ];
    }

    public static function wmoIcon(int $code): string
    {
        return ConditionMapper::icon(ConditionMapper::fromWmoCode($code));
    }
}
