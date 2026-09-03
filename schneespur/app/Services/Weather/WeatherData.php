<?php

namespace App\Services\Weather;

use Carbon\Carbon;

final readonly class WeatherData
{
    public function __construct(
        public float $temperature_c,
        public float $precipitation_mm,
        /** Schneehöhe in Zentimetern. */
        public float $snow_depth_cm,
        public float $wind_kmh,
        public int $humidity_percent,
        public string $condition,
        public ?int $weather_code,
        public string $provider,
        public Carbon $observed_at,
    ) {}

    public function toArray(): array
    {
        return [
            'temperature_c' => $this->temperature_c,
            'precipitation_mm' => $this->precipitation_mm,
            'snow_depth_cm' => $this->snow_depth_cm,
            'wind_kmh' => $this->wind_kmh,
            'humidity_percent' => $this->humidity_percent,
            'condition' => $this->condition,
            'weather_code' => $this->weather_code,
            'provider' => $this->provider,
            'observed_at' => $this->observed_at->toIso8601String(),
        ];
    }
}
