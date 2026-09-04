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

    /**
     * Bekannte Adress-Teilzusammensetzungen außerhalb des Formatters — je mit
     * dem genauen Text der betroffenen Zeile statt ihrer Nummer, denn die
     * Nummer verschiebt sich mit jeder Änderung darüber, der Text nicht.
     * Verschwindet ein gelisteter Text aus seiner Datei, meldet der Test die
     * Ausnahme als Karteileiche — dann gehört sie entfernt, nicht
     * stehengelassen. Eine ganze Datei freizuzeichnen wäre der falsche
     * Hebel: dann würde eine spätere, echte Adressausgabe in derselben Datei
     * lautlos durchrutschen.
     *
     * driver/dashboard.blade.php enthält daneben zwei weitere Stellen, die
     * bewußt nur Teile einer Adresse anfassen, aber unten keinen Eintrag
     * brauchen, weil kein Erkennungsmuster auf sie feuert: Zeile ~92 gibt in
     * einer auf 120px beschnittenen Zeile nur die Straße aus (zip/city
     * kommen dort gar nicht vor — eine vollständige Adresse wäre falsch,
     * nicht richtiger), und Zeile ~337 trägt street/city in ein PHP-Array für
     * ein JS-Array — Datentransport, keine Ausgabe. Sie stehen hier nur
     * schriftlich, damit ihre Abwesenheit in der Liste nicht wie eine Lücke
     * aussieht.
     */
    private const ADDRESS_ALLOWED = [
        'resources/views/driver/dashboard.blade.php' => [
            "x-text=\"[obj.street, obj.city].filter(Boolean).join(', ')\""
                => 'street, city ist regionsneutral — der gemeinsame Anfang aller drei '
                    .'Adressmuster, kein regionaler Unterschied wird hier sichtbar; die Zeile '
                    .'ist auf 160px beschnitten, wo die volle Adresse den Ort aus dem '
                    .'Sichtfeld drängen und die Objekt-Auswahl des Fahrers in jeder Region '
                    .'verschlechtern würde.',
        ],
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

    /**
     * Die Schneehöhe ist seit 1.2.1 nullable: Bright Sky (DWD) und MET Norway
     * melden sie gar nicht, und „nie gemessen" darf in einem Einsatznachweis
     * nicht als „0,0 cm" erscheinen. Genau das macht ein (float)-Cast vor dem
     * Formatter wieder — er wandelt das null zurück in eine Null. Fünf
     * Anzeigestellen hatten ihn; keine darf ihn zurückbekommen.
     */
    public function test_no_display_site_casts_a_missing_snow_depth_to_zero(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path) {
            if (preg_match('/format_snow_depth\(\s*\(float\)/', (string) file_get_contents($path))) {
                $offenders[] = $this->relative($path);
            }
        }

        $this->assertSame([], $offenders, 'A (float) cast turns a never-measured snow depth back into "0.0 cm"');
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
     * Zehn Views setzten die Adresse vor 1.2.1 jede für sich zusammen, in
     * vier verschiedenen Schreibweisen — keine kannte den Bundesstaat, keine
     * ließ sich auf ein US-Format umstellen. Die elfte darf nicht wieder so
     * anfangen.
     *
     * Drei Zusammensetzungsmuster zählen — die vier historischen Schreibweisen,
     * die 331664c ersetzt hat, brauchen zwei Blade-Muster plus das
     * JS/Alpine-Muster, um alle zu treffen:
     * - ein Blade-Echo mit Pfeilzugriff auf zip/city, egal ob direkt
     *   (`{{ $o->zip }}`), verschachtelt in implode()/array_filter() oder
     *   sonst irgendwo innerhalb desselben `{{ … }}` (`{{[^}]*->(zip|city)`);
     * - ein Blade-Echo einer bloßen $companyZip/$companyCity-Variable, wie
     *   sie die PDF-Kopfzeile vor 331664c benutzte (`{{[^}]*\$\w*(Zip|City)`);
     * - eine JS/Alpine-Zusammensetzung über Punktzugriff (`obj.city`), wie
     *   sie ein x-text mit .join() zusammenklebt.
     * Der Zugriff im @if ($object->zip) ist erlaubt — die Frage "gibt es
     * hier eine Adresse?" ist keine Formatierung, und ohne `{{` feuert
     * keines der beiden Blade-Muster.
     *
     * (trim("$street, $zip $city", ', ') — die elfte, in app/helpers.php —
     * bleibt hier bewußt außen vor: kein Blade-Echo, und dieser Test prüft
     * nur resources/views/.)
     *
     * Die Ausnahme wirkt zeilenweise über einen Fingerabdruck (den genauen
     * Text des bekannten Ausschnitts), nicht dateiweit: der Fingerabdruck
     * wird aus der Zeile herausgeschnitten, bevor die Zeile auf ein
     * Erkennungsmuster geprüft wird — eine zweite, neue Zusammensetzung auf
     * derselben Zeile wie eine bekannte Ausnahme fällt dadurch trotzdem auf.
     * Verschwindet ein gelisteter Fingerabdruck aus seiner Datei — etwa weil
     * die Zeile umgeschrieben wurde —, meldet dieser Test die Ausnahme als
     * Karteileiche.
     */
    public function test_no_view_composes_an_address_by_hand(): void
    {
        $offenders = [];
        $matchedFingerprints = [];

        foreach ($this->sources() as $path) {
            $relative = $this->relative($path);

            if (! str_starts_with($relative, 'resources/views/')) {
                continue;
            }

            $allowedHere = self::ADDRESS_ALLOWED[$relative] ?? [];
            $lines = explode("\n", (string) file_get_contents($path));

            foreach ($lines as $line) {
                // Bekannte Fingerabdrücke aus der Zeile herausschneiden, bevor
                // sie geprüft wird — sonst würde ein zweiter, neuer Verstoß
                // auf derselben Zeile stillschweigend mit durchrutschen.
                $remainder = $line;

                foreach ($allowedHere as $fingerprint => $because) {
                    if (str_contains($remainder, $fingerprint)) {
                        $matchedFingerprints["{$relative}\0{$fingerprint}"] = true;
                        $remainder = str_replace($fingerprint, '', $remainder);
                    }
                }

                $isOffense = preg_match('/\{\{[^}]*->(zip|city)\b/', $remainder) === 1
                    || preg_match('/\{\{[^}]*\$\w*(Zip|City)\b/', $remainder) === 1
                    || preg_match('/\.(zip|city)\b/', $remainder) === 1;

                if ($isOffense) {
                    $offenders[] = "{$relative}: ".trim($line);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Use format_address()/format_address_lines() instead of composing an address in the view. '
                .'Known-safe partial compositions are listed in ADDRESS_ALLOWED with their reasons — '
                .'if this line is one of them, add or update its fingerprint there instead of silencing the test.'
        );

        // Ein Eintrag in ADDRESS_ALLOWED, der auf nichts mehr paßt, ist eine
        // Karteileiche — die Begründung ist dann für eine Zeile geschrieben,
        // die es nicht mehr gibt.
        $stale = [];

        foreach (self::ADDRESS_ALLOWED as $relative => $fingerprints) {
            foreach ($fingerprints as $fingerprint => $because) {
                if (! isset($matchedFingerprints["{$relative}\0{$fingerprint}"])) {
                    $stale[] = "{$relative}: '{$fingerprint}' no longer found — remove or update this exception (was: {$because})";
                }
            }
        }

        $this->assertSame([], $stale, 'A listed address exception has gone stale');
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

        // Das Pfeilzugriff-Muster muß auf dem direkten Echo feuern, auf der
        // verschachtelten implode()/array_filter()-Schreibweise (die alte,
        // schmalere Fassung hätte das übersehen), und beim @if schweigen, das
        // nur fragt, ob überhaupt eine Adresse da ist — ohne `{{` feuert es
        // gar nicht erst.
        $this->assertSame(1, preg_match('/\{\{[^}]*->(zip|city)\b/', '{{ $object->zip }} {{ $object->city }}'));
        $this->assertSame(1, preg_match('/\{\{[^}]*->(zip|city)\b/', '{{ $o->city }}</span>'));
        $this->assertSame(1, preg_match('/\{\{[^}]*->(zip|city)\b/', "{{ implode(' ', array_filter([\$object->zip, \$object->city])) }}"));
        $this->assertSame(0, preg_match('/\{\{[^}]*->(zip|city)\b/', '@if ($object->zip || $object->city)'));

        // Die bloße-Variable-Schreibweise der PDF-Kopfzeile vor 331664c
        // ($companyZip/$companyCity) hat keinen Pfeilzugriff und braucht ein
        // eigenes Muster.
        $this->assertSame(1, preg_match('/\{\{[^}]*\$\w*(Zip|City)\b/', '{{ $companyZip }} {{ $companyCity }}'));
        $this->assertSame(0, preg_match('/\{\{[^}]*\$\w*(Zip|City)\b/', '{{ $customer->name }}'));

        // Die JS/Alpine-Variante über Punktzugriff muß auf einer echten
        // Zusammensetzung feuern und bei einem bloß ähnlich aussehenden
        // Bezeichner ("...pacity") stillbleiben, der kein Punkt-Präfix hat.
        $this->assertSame(1, preg_match('/\.(zip|city)\b/', "[obj.street, obj.city].filter(Boolean).join(', ')"));
        $this->assertSame(0, preg_match('/\.(zip|city)\b/', '{{ $object->capacity }}'));
    }
}
