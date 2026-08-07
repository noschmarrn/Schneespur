<?php

namespace App\Services\Format;

use App\Models\Setting;
use DateTimeInterface;
use DateTimeZone;

/**
 * The single place that turns canonical values into what an operator reads.
 *
 * Canonical means: money in integer cents, temperature in Celsius, speed in
 * km/h, distance in metres, weight in kilograms, timestamps in UTC. Switching a
 * setting never changes a stored value — only what this class prints.
 *
 * Bound as a singleton (AppServiceProvider) because it reads nine settings and
 * a table with thirty timestamps would otherwise issue thirty queries.
 */
class Formatter
{
    public const PLACEHOLDER = '—';

    /** Applied when a setting is missing — the 'de' preset's values. */
    private const DEFAULTS = [
        'currency_code' => 'EUR',
        'currency_position' => 'after',
        'number_format' => 'dot_comma',
        'date_format' => 'dmy_dot',
        'time_format' => '24h',
        'unit_distance' => 'metric',
        'unit_temperature' => 'celsius',
        'unit_weight' => 'metric',
    ];

    private const DATE_PATTERNS = [
        'dmy_dot' => 'd.m.Y',
        'dmy_slash' => 'd/m/Y',
        'mdy_slash' => 'm/d/Y',
        'iso' => 'Y-m-d',
    ];

    private const TIME_PATTERNS = [
        '24h' => 'H:i',
        '12h' => 'g:i A',
    ];

    /**
     * Second precision, for evidence: the GPS trace in an Einsatznachweis
     * needs it. It is the same setting, only a longer pattern — a separate
     * "seconds" setting would just be a way to contradict the first one.
     */
    private const TIME_PATTERNS_WITH_SECONDS = [
        '24h' => 'H:i:s',
        '12h' => 'g:i:s A',
    ];

    /** decimal separator, thousands separator */
    private const NUMBER_SEPARATORS = [
        'dot_comma' => [',', '.'],
        'comma_dot' => ['.', ','],
        'apostrophe_dot' => ['.', "'"],
    ];

    private const CURRENCY_SYMBOLS = [
        'EUR' => '€',
        'USD' => '$',
        'CAD' => '$',
        'GBP' => '£',
        'CHF' => 'CHF',
        'CZK' => 'Kč',
    ];

    /** @param array<string, string> $settings */
    public function __construct(private readonly array $settings) {}

    public static function fromSettings(): self
    {
        $resolved = [];

        foreach (self::DEFAULTS as $key => $fallback) {
            $resolved[$key] = (string) (Setting::get($key) ?? $fallback);
        }

        return new self($resolved);
    }

    private function setting(string $key): string
    {
        return $this->settings[$key] ?? self::DEFAULTS[$key];
    }

    public function date(?DateTimeInterface $moment): string
    {
        return $this->formatMoment($moment, self::DATE_PATTERNS[$this->setting('date_format')] ?? 'd.m.Y');
    }

    public function time(?DateTimeInterface $moment, bool $withSeconds = false): string
    {
        $key = $this->setting('time_format');

        $pattern = $withSeconds
            ? (self::TIME_PATTERNS_WITH_SECONDS[$key] ?? 'H:i:s')
            : (self::TIME_PATTERNS[$key] ?? 'H:i');

        return $this->formatMoment($moment, $pattern);
    }

    /**
     * The three values the browser needs, handed to it by the layout.
     *
     * Only three: the running clock on the driver dashboard and the updater's
     * signed_at date are the sole places the server cannot render. Everything
     * else is formatted here — do not grow this list to "the settings".
     *
     * @return array{timeFormat: string, dateFormat: string, numberFormat: string}
     */
    public function browserSettings(): array
    {
        return [
            'timeFormat' => $this->setting('time_format'),
            'dateFormat' => $this->setting('date_format'),
            'numberFormat' => $this->setting('number_format'),
        ];
    }

    public function dateTime(?DateTimeInterface $moment): string
    {
        if ($moment === null) {
            return self::PLACEHOLDER;
        }

        return $this->date($moment) . ' ' . $this->time($moment);
    }

    private function formatMoment(?DateTimeInterface $moment, string $pattern): string
    {
        if ($moment === null) {
            return self::PLACEHOLDER;
        }

        // Stored in UTC, read in the operator's timezone. Doing this here is
        // the point: the call sites that forgot it are the reason for it.
        $zone = new DateTimeZone((string) config('app.display_timezone', 'UTC'));

        return \DateTimeImmutable::createFromInterface($moment)
            ->setTimezone($zone)
            ->format($pattern);
    }

    public function number(?float $value, int $decimals = 1): string
    {
        if ($value === null) {
            return self::PLACEHOLDER;
        }

        [$decimal, $thousands] = self::NUMBER_SEPARATORS[$this->setting('number_format')]
            ?? self::NUMBER_SEPARATORS['dot_comma'];

        return number_format($value, $decimals, $decimal, $thousands);
    }

    public function money(?int $cents): string
    {
        if ($cents === null) {
            return self::PLACEHOLDER;
        }

        $amount = $this->number($cents / 100, 2);
        $symbol = $this->currencySymbol();

        return $this->setting('currency_position') === 'before'
            ? $symbol . ($symbol === $this->setting('currency_code') ? ' ' : '') . $amount
            : $amount . ' ' . $symbol;
    }

    public function parseDecimal(string $input): ?float
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        [$decimal, $thousands] = self::NUMBER_SEPARATORS[$this->setting('number_format')]
            ?? self::NUMBER_SEPARATORS['dot_comma'];

        // Strictly the configured separators — no guessing. "12,34,56" in a
        // comma-decimal locale is a typo, and silently reading it as 123456
        // would put a wrong price in a contract.
        $normalised = str_replace([$thousands, "\u{00A0}", ' '], '', $input);
        $normalised = str_replace($decimal, '.', $normalised);

        return is_numeric($normalised) ? (float) $normalised : null;
    }

    public function parseMoneyToCents(string $input): ?int
    {
        $value = $this->parseDecimal($input);

        return $value === null ? null : (int) round($value * 100);
    }

    /**
     * The bare symbol, for labels like "Price (€)" that carry it in a sentence.
     * Prints the ISO code when we have no symbol for it.
     */
    public function currencySymbol(): string
    {
        $code = $this->setting('currency_code');

        return self::CURRENCY_SYMBOLS[$code] ?? $code;
    }

    public function temperature(?float $celsius): string
    {
        if ($celsius === null) {
            return self::PLACEHOLDER;
        }

        return $this->setting('unit_temperature') === 'fahrenheit'
            ? $this->number($celsius * 9 / 5 + 32, 1) . ' ' . __('format.unit_fahrenheit')
            : $this->number($celsius, 1) . ' ' . __('format.unit_celsius');
    }

    public function speed(?float $kmh): string
    {
        if ($kmh === null) {
            return self::PLACEHOLDER;
        }

        // Speed follows the distance unit: no country measures in miles and
        // drives in km/h, so a separate setting would only add a way to be
        // inconsistent.
        return $this->setting('unit_distance') === 'imperial'
            ? $this->number($kmh * 0.621371, 1) . ' ' . __('format.unit_mph')
            : $this->number($kmh, 1) . ' ' . __('format.unit_kmh');
    }

    public function distance(?float $metres): string
    {
        if ($metres === null) {
            return self::PLACEHOLDER;
        }

        if ($this->setting('unit_distance') === 'imperial') {
            $miles = $metres / 1609.344;

            return $miles < 1.0
                ? $this->number($metres * 3.28084, 0) . ' ' . __('format.unit_foot')
                : $this->number($miles, 1) . ' ' . __('format.unit_mile');
        }

        return $metres < 1000.0
            ? $this->number($metres, 0) . ' ' . __('format.unit_metre')
            : $this->number($metres / 1000, 1) . ' ' . __('format.unit_kilometre');
    }

    public function weight(?float $kilograms): string
    {
        if ($kilograms === null) {
            return self::PLACEHOLDER;
        }

        if ($this->setting('unit_weight') === 'imperial') {
            $pounds = $kilograms * 2.20462;

            // Short ton = 2000 lb (US), not the British long ton of 2240.
            return $pounds < 2000.0
                ? $this->number($pounds, 0) . ' ' . __('format.unit_pound')
                : $this->number($pounds / 2000, 1) . ' ' . __('format.unit_short_ton');
        }

        return $kilograms < 1000.0
            ? $this->number($kilograms, 0) . ' ' . __('format.unit_kilogram')
            : $this->number($kilograms / 1000, 1) . ' ' . __('format.unit_tonne');
    }
}
