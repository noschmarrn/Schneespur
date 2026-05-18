<?php

namespace App\Services\Weather;

class OpenMeteoFreeProvider extends OpenMeteoBase
{
    protected function providerSlug(): string
    {
        return 'openmeteo_free';
    }

    protected function buildQuery(float $lat, float $lon): array
    {
        return [
            'latitude' => $lat,
            'longitude' => $lon,
            'current' => static::CURRENT_PARAMS,
            'timezone' => 'auto',
        ];
    }

    public function name(): string
    {
        return 'Open-Meteo (Free)';
    }

    public function requiresApiKey(): bool
    {
        return false;
    }
}
