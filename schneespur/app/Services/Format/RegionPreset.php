<?php

namespace App\Services\Format;

/**
 * One region's formatting defaults. Immutable: a preset is a template that
 * fills the settings, never a live source the Formatter reads from — the
 * operator may change any single field afterwards.
 */
final class RegionPreset
{
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $currencyCode,
        public readonly string $currencyPosition,
        public readonly string $numberFormat,
        public readonly string $dateFormat,
        public readonly string $timeFormat,
        public readonly string $unitDistance,
        public readonly string $unitTemperature,
        public readonly string $unitWeight,
    ) {}

    /** @return array<string, string> setting key => value */
    public function toSettings(): array
    {
        return [
            'format_region' => $this->code,
            'currency_code' => $this->currencyCode,
            'currency_position' => $this->currencyPosition,
            'number_format' => $this->numberFormat,
            'date_format' => $this->dateFormat,
            'time_format' => $this->timeFormat,
            'unit_distance' => $this->unitDistance,
            'unit_temperature' => $this->unitTemperature,
            'unit_weight' => $this->unitWeight,
        ];
    }
}
