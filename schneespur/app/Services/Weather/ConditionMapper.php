<?php

namespace App\Services\Weather;

final class ConditionMapper
{
    private const WMO_MAP = [
        0 => 'clear',
        1 => 'clear',
        2 => 'cloudy',
        3 => 'cloudy',
        45 => 'fog',
        48 => 'fog',
        51 => 'drizzle',
        53 => 'drizzle',
        55 => 'drizzle',
        56 => 'drizzle',
        57 => 'drizzle',
        61 => 'rain',
        63 => 'rain',
        65 => 'rain',
        66 => 'rain',
        67 => 'rain',
        71 => 'snow',
        73 => 'snow',
        75 => 'snow',
        77 => 'snow',
        80 => 'rain-shower',
        81 => 'rain-shower',
        82 => 'rain-shower',
        85 => 'snow-shower',
        86 => 'snow-shower',
        95 => 'thunderstorm',
        96 => 'thunderstorm',
        99 => 'thunderstorm',
    ];

    private const MET_NORWAY_MAP = [
        'clearsky' => 'clear',
        'fair' => 'clear',
        'partlycloudy' => 'cloudy',
        'cloudy' => 'cloudy',
        'fog' => 'fog',
        'lightrain' => 'drizzle',
        'lightrainshowers' => 'drizzle',
        'rain' => 'rain',
        'heavyrain' => 'rain',
        'rainshowers' => 'rain-shower',
        'heavyrainshowers' => 'rain-shower',
        'lightsleet' => 'drizzle',
        'lightsleetshowers' => 'drizzle',
        'sleet' => 'rain',
        'heavysleet' => 'rain',
        'sleetshowers' => 'rain-shower',
        'heavysleetshowers' => 'rain-shower',
        'lightsnow' => 'snow',
        'snow' => 'snow',
        'heavysnow' => 'snow',
        'lightsnowshowers' => 'snow-shower',
        'snowshowers' => 'snow-shower',
        'heavysnowshowers' => 'snow-shower',
        'lightrainandthunder' => 'thunderstorm',
        'rainandthunder' => 'thunderstorm',
        'heavyrainandthunder' => 'thunderstorm',
        'lightrainshowersandthunder' => 'thunderstorm',
        'rainshowersandthunder' => 'thunderstorm',
        'heavyrainshowersandthunder' => 'thunderstorm',
        'lightsleetandthunder' => 'thunderstorm',
        'sleetandthunder' => 'thunderstorm',
        'heavysleetandthunder' => 'thunderstorm',
        'lightsleetshowersandthunder' => 'thunderstorm',
        'sleetshowersandthunder' => 'thunderstorm',
        'heavysleetshowersandthunder' => 'thunderstorm',
        'lightsnowandthunder' => 'thunderstorm',
        'snowandthunder' => 'thunderstorm',
        'heavysnowandthunder' => 'thunderstorm',
        'lightsnowshowersandthunder' => 'thunderstorm',
        'snowshowersandthunder' => 'thunderstorm',
        'heavysnowshowersandthunder' => 'thunderstorm',
        'lightssleetshowersandthunder' => 'thunderstorm',
        'lightssnowshowersandthunder' => 'thunderstorm',
    ];

    private const ICON_MAP = [
        'clear' => 'sun',
        'cloudy' => 'cloud',
        'fog' => 'fog',
        'drizzle' => 'drizzle',
        'rain' => 'rain',
        'snow' => 'snow',
        'rain-shower' => 'rain-shower',
        'snow-shower' => 'snow-shower',
        'thunderstorm' => 'thunderstorm',
    ];

    public static function fromWmoCode(?int $code): string
    {
        if ($code === null) {
            return 'cloudy';
        }

        return self::WMO_MAP[$code] ?? 'cloudy';
    }

    public static function icon(string $condition): string
    {
        return self::ICON_MAP[$condition] ?? 'cloud';
    }

    public static function label(string $condition): string
    {
        return __('weather.condition_' . $condition);
    }

    public static function fromMetNorwaySymbol(string $symbolCode): string
    {
        $base = preg_replace('/_(?:day|night|polartwilight)$/', '', $symbolCode);

        return self::MET_NORWAY_MAP[$base] ?? 'cloudy';
    }

    public static function allConditions(): array
    {
        return array_values(array_unique(array_values(self::WMO_MAP)));
    }
}
