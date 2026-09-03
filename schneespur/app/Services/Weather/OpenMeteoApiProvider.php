<?php

namespace App\Services\Weather;

use App\Models\Setting;

class OpenMeteoApiProvider extends OpenMeteoBase
{
    protected const API_URL = 'https://customer-api.open-meteo.com/v1/forecast';

    protected const ARCHIVE_URL = 'https://customer-archive-api.open-meteo.com/v1/archive';

    protected function providerSlug(): string
    {
        return 'openmeteo_api';
    }

    protected function buildQuery(float $lat, float $lon): array
    {
        return [
            'latitude' => $lat,
            'longitude' => $lon,
            'current' => static::CURRENT_PARAMS,
            'timezone' => 'auto',
            'apikey' => Setting::get('weather_api_key', ''),
        ];
    }

    protected function buildArchiveQuery(float $lat, float $lon, \Carbon\CarbonInterface $hour): array
    {
        return parent::buildArchiveQuery($lat, $lon, $hour) + [
            'apikey' => Setting::get('weather_api_key', ''),
        ];
    }

    public function name(): string
    {
        return 'Open-Meteo (API-Key)';
    }

    public function requiresApiKey(): bool
    {
        return true;
    }
}
