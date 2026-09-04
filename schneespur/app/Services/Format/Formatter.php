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
        'address_format' => 'street_zip_city',
        'csv_delimiter' => ';',
        'week_starts_on' => '1',
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
     * The four values the browser needs, handed to it by the layout.
     *
     * Only four: the running clock on the driver dashboard and the updater's
     * signed_at date are the sole places the server cannot render. Everything
     * else is formatted here — do not grow this list to "the settings".
     *
     * timeZone joined in 1.2.1 and is the one entry that could not have been
     * left out. The other three change how a value looks; this one changes
     * which value it is. Without it the browser can only use the device's
     * zone — right by accident in Passau, wrong on a phone still standing on
     * the zone of the last holiday. It is spelled the way
     * Intl.DateTimeFormat spells its option, on purpose: a second name at
     * this seam would be nothing but a place to make a mistake.
     *
     * @return array{timeFormat: string, dateFormat: string, numberFormat: string, timeZone: string}
     */
    public function browserSettings(): array
    {
        return [
            'timeFormat' => $this->setting('time_format'),
            'dateFormat' => $this->setting('date_format'),
            'numberFormat' => $this->setting('number_format'),
            'timeZone' => (string) config('app.display_timezone', 'UTC'),
        ];
    }

    /**
     * Die drei regionalen Einstellungen, die keine Zahl formatieren, aber
     * denselben Weg gehen: Preset füllt sie, der Betrieb darf sie ändern, und
     * genau eine Klasse liest sie.
     */
    public function addressFormat(): string
    {
        return $this->setting('address_format');
    }

    /**
     * Eine Adresse als Liste von Zeilen — die Wahrheit, aus der die einzeilige
     * Form entsteht.
     *
     * Zehn Views setzten das vorher jede für sich zusammen, in vier
     * verschiedenen Schreibweisen, und keine kannte den Bundesstaat.
     *
     * Rückgabe ist ein leeres Array, kein PLACEHOLDER: die Views entscheiden
     * mit ihrem eigenen @if, ob sie etwas zeigen.
     *
     * @param  array<string, ?string>|object|null  $source  Ein Model, ein Array
     *   mit street/zip/city/state, oder null.
     * @return list<string>
     */
    public function addressLines(mixed $source): array
    {
        $parts = $this->addressParts($source);

        $street = trim((string) ($parts['street'] ?? ''));
        $zip = trim((string) ($parts['zip'] ?? ''));
        $city = trim((string) ($parts['city'] ?? ''));
        $state = trim((string) ($parts['state'] ?? ''));

        $lines = [];

        if ($street !== '') {
            $lines[] = $street;
        }

        $locality = match ($this->addressFormat()) {
            // "Minneapolis, MN 55401" — aber ohne Bundesstaat bliebe sonst ein
            // hängendes Komma stehen: "Minneapolis, 55401".
            'street_city_state_zip' => $state === ''
                ? trim($city.' '.$zip)
                : implode(', ', array_filter([$city, trim($state.' '.$zip)])),
            // "London SW1A 2AA" — kein Komma vor der Postleitzahl.
            'street_city_zip' => trim($city.' '.$zip),
            // "94032 Passau"
            default => trim($zip.' '.$city),
        };

        if ($locality !== '') {
            $lines[] = $locality;
        }

        return $lines;
    }

    /**
     * Die einzeilige Form. Sie ist genau das Verbinden der Zeilen mit ", " —
     * in jedem der drei Muster steht die Straße auf einer eigenen Zeile und
     * der Rest gehört zusammen.
     *
     * @param  array<string, ?string>|object|null  $source
     */
    public function address(mixed $source): string
    {
        return implode(', ', $this->addressLines($source));
    }

    /**
     * @param  array<string, ?string>|object|null  $source
     * @return array<string, ?string>
     */
    private function addressParts(mixed $source): array
    {
        if (is_array($source)) {
            return $source;
        }

        if ($source === null) {
            return [];
        }

        // Duck-typed statt gegen CustomerObject getypt: dieselbe Methode
        // formatiert die Betriebsadresse aus den Einstellungen und, ab Phase 4,
        // die Kundenadresse.
        return [
            'street' => $source->street ?? null,
            'zip' => $source->zip ?? null,
            'city' => $source->city ?? null,
            'state' => $source->state ?? null,
        ];
    }

    public function csvDelimiter(): string
    {
        return $this->setting('csv_delimiter');
    }

    /** 1 = Montag, 7 = Sonntag. Der einzige Ort, an dem der Text zur Zahl wird. */
    public function weekStartsOn(): int
    {
        return $this->setting('week_starts_on') === '7' ? 7 : 1;
    }

    public function dateTime(?DateTimeInterface $moment, bool $withSeconds = false): string
    {
        if ($moment === null) {
            return self::PLACEHOLDER;
        }

        return $this->date($moment) . ' ' . $this->time($moment, $withSeconds);
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

    /**
     * Die Einheit für Schneehöhen und Räumschwellen: 'cm' oder 'in'.
     *
     * Sie folgt unit_distance und hat bewußt keine eigene Einstellung — siehe
     * die Begründung an snowDepth(). Diese Methode ist der Ort, an dem diese
     * Entscheidung einmal steht, statt an jeder Stelle neu getroffen zu werden.
     */
    public function snowUnit(): string
    {
        return $this->setting('unit_distance') === 'imperial' ? 'in' : 'cm';
    }

    /** Die übersetzte Abkürzung derselben Einheit, für Feldbeschriftungen. */
    public function snowUnitLabel(): string
    {
        return $this->snowUnit() === 'in'
            ? __('format.unit_inch')
            : __('format.unit_centimetre');
    }

    /**
     * Snow depth, stored in centimetres.
     *
     * Follows unit_distance rather than carrying a setting of its own: nobody
     * measures the road in miles and the snow on it in centimetres. In a
     * winter-service Einsatznachweis this is the number the whole document is
     * about, so it must not be the one that stayed metric.
     */
    public function snowDepth(?float $centimetres): string
    {
        if ($centimetres === null) {
            return self::PLACEHOLDER;
        }

        return $this->snowUnit() === 'in'
            ? $this->number($centimetres / 2.54, 1) . ' ' . __('format.unit_inch')
            : $this->number($centimetres, 1) . ' ' . __('format.unit_centimetre');
    }

    /**
     * Precipitation, stored in millimetres.
     *
     * Two decimals in inches, one in millimetres: a millimetre is 0.04 in, so
     * a single decimal would round a real rainfall down to "0.0 in".
     */
    public function precipitation(?float $millimetres): string
    {
        if ($millimetres === null) {
            return self::PLACEHOLDER;
        }

        return $this->setting('unit_distance') === 'imperial'
            ? $this->number($millimetres / 25.4, 2) . ' ' . __('format.unit_inch')
            : $this->number($millimetres, 1) . ' ' . __('format.unit_millimetre');
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
