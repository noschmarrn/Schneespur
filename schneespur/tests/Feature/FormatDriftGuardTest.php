<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Formatting drifts back without a guard. The evidence is in the repository:
 * lang/en/customer_object.php said "Price (€)" — the English translation
 * shipped a Euro sign, and nobody noticed for releases.
 *
 * Same shape as the Str::markdown guard from M018/S02.
 */
class FormatDriftGuardTest extends TestCase
{
    /** Files that legitimately contain a machine format or a raw symbol. */
    private const ALLOWED = [
        'app/Services/Format/Formatter.php',
        'resources/views/components/date-range-presets.blade.php',
        'lang/de/format.php',
        'lang/en/format.php',
    ];

    /** @return list<string> */
    private function sources(): array
    {
        $files = [];

        foreach ([base_path('app'), base_path('resources/views'), base_path('lang')] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function relative(string $path): string
    {
        return str_replace(base_path().'/', '', $path);
    }

    public function test_no_hardcoded_german_date_format_outside_the_formatter(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path) {
            $relative = $this->relative($path);

            if (in_array($relative, self::ALLOWED, true)) {
                continue;
            }

            if (preg_match("/format\('d\.m\.Y/", (string) file_get_contents($path))) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'Use format_date()/format_datetime() instead of a hardcoded pattern');
    }

    /**
     * H:i is the other half: a hardcoded clock ignores the 12h setting just as
     * a hardcoded date ignores mdy_slash. Machine formats are exempt by their
     * own rule below, not by living in this pattern.
     */
    public function test_no_hardcoded_clock_format_outside_the_formatter(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path) {
            $relative = $this->relative($path);

            if (in_array($relative, self::ALLOWED, true)) {
                continue;
            }

            $contents = (string) file_get_contents($path);

            // 'Y-m-d\TH:i' and 'Y-m-d H:i:s' are machine formats — an
            // <input type="datetime-local"> value and a database string. Only
            // a bare clock pattern is a display decision.
            if (preg_match("/format\('H:i/", $contents) && ! preg_match("/format\('Y-m-d.{0,3}H:i/", $contents)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'Use format_time() instead of a hardcoded clock pattern');
    }

    public function test_no_hardcoded_units_or_currency_symbols(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path) {
            $relative = $this->relative($path);

            if (in_array($relative, self::ALLOWED, true)) {
                continue;
            }

            $contents = (string) file_get_contents($path);

            foreach (['€', 'km/h', '°C'] as $needle) {
                if (str_contains($contents, $needle)) {
                    $offenders[] = "{$relative} ({$needle})";
                }
            }

            // mm and cm cannot be searched as bare strings — "comment" contains
            // one and the PDF stylesheet's "@page { margin: 15mm }" the other.
            // Only a unit glued to a printed value or a quoted suffix counts.
            foreach (['/\}\}\s*(mm|cm)\b/' => 'appended to a Blade echo',
                '/\'\s(mm|cm)\'/' => 'a quoted suffix',
                '/:\w+\s(mm|cm)\b/' => 'baked into a translation'] as $pattern => $what) {
                if (preg_match($pattern, $contents)) {
                    $offenders[] = "{$relative} ({$what})";
                }
            }
        }

        $this->assertSame([], $offenders, 'Units and currency symbols belong in lang/*/format.php, printed by the Formatter');
    }

    /**
     * The guard is only worth having if it actually fires. This proves the
     * pattern matches what it claims to match, so a future refactor cannot
     * quietly turn the assertions above into "no files, therefore green".
     */
    public function test_the_guard_recognises_an_offender(): void
    {
        $this->assertNotEmpty($this->sources());
        $this->assertSame(1, preg_match("/format\('d\.m\.Y/", "\$d->format('d.m.Y H:i')"));
        $this->assertSame(1, preg_match("/format\('H:i/", "\$d->format('H:i')"));
        $this->assertSame(0, preg_match("/format\('H:i/", "\$d->format('Y-m-d')"));

        // The mm/cm patterns must fire on a real offender and stay quiet on the
        // two false friends that made a bare substring search impossible.
        $this->assertSame(1, preg_match('/\}\}\s*(mm|cm)\b/', '{{ $snapshot->snow_depth }} cm'));
        $this->assertSame(1, preg_match('/\'\s(mm|cm)\'/', "number_format(\$x, 1) . ' mm'"));
        $this->assertSame(1, preg_match('/:\w+\s(mm|cm)\b/', 'Wetter: :precipitation mm Niederschlag'));
        $this->assertSame(0, preg_match('/\}\}\s*(mm|cm)\b/', '{{-- a comment --}}'));
        $this->assertSame(0, preg_match('/\'\s(mm|cm)\'/', '@page { margin: 15mm 15mm 18mm 15mm; }'));
    }
}
