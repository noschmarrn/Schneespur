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
        // Ab 1.2.1. Am Ende und mit Vorgabewerten, damit ein Modul, das ein
        // eigenes Preset registriert, ohne Änderung weiterläuft — siehe
        // RegionRegistryTest::test_a_module_preset_without_the_new_fields…
        public readonly string $addressFormat = 'street_zip_city',
        public readonly string $csvDelimiter = ';',
        /** 1 = Montag, 7 = Sonntag, wie Carbon::dayOfWeekIso. */
        public readonly int $weekStartsOn = 1,
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
            'address_format' => $this->addressFormat,
            'csv_delimiter' => $this->csvDelimiter,
            // Die Tabelle hält Text. Zurückgelesen wird der int genau einmal,
            // in Formatter::weekStartsOn().
            'week_starts_on' => (string) $this->weekStartsOn,
        ];
    }
}
