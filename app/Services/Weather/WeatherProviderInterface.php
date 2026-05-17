<?php

namespace App\Services\Weather;

interface WeatherProviderInterface
{
    public function fetchCurrent(float $lat, float $lon): ?WeatherData;

    /**
     * @return array{ok: bool, message: string, latency_ms: int}
     */
    public function testConnection(float $lat, float $lon): array;

    public function name(): string;

    public function requiresApiKey(): bool;
}
