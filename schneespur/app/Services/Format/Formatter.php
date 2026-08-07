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

    public function time(?DateTimeInterface $moment): string
    {
        return $this->formatMoment($moment, self::TIME_PATTERNS[$this->setting('time_format')] ?? 'H:i');
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
}
