<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Setting;
use App\Services\Format\Formatter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FormatAddressTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function formatter(string $pattern): Formatter
    {
        Setting::set('address_format', $pattern);
        app()->forgetInstance(Formatter::class);

        return app(Formatter::class);
    }

    private const PASSAU = [
        'street' => 'Innstraße 12',
        'zip' => '94032',
        'city' => 'Passau',
        'state' => '',
    ];

    private const MINNEAPOLIS = [
        'street' => '400 S 4th St',
        'zip' => '55401',
        'city' => 'Minneapolis',
        'state' => 'MN',
    ];

    private const LONDON = [
        'street' => '10 Downing St',
        'zip' => 'SW1A 2AA',
        'city' => 'London',
        'state' => '',
    ];

    public function test_german_pattern(): void
    {
        $f = $this->formatter('street_zip_city');

        $this->assertSame(['Innstraße 12', '94032 Passau'], $f->addressLines(self::PASSAU));
        $this->assertSame('Innstraße 12, 94032 Passau', $f->address(self::PASSAU));
    }

    public function test_us_pattern_puts_the_state_before_the_zip(): void
    {
        $f = $this->formatter('street_city_state_zip');

        $this->assertSame(['400 S 4th St', 'Minneapolis, MN 55401'], $f->addressLines(self::MINNEAPOLIS));
        $this->assertSame('400 S 4th St, Minneapolis, MN 55401', $f->address(self::MINNEAPOLIS));
    }

    public function test_british_pattern_has_no_comma_before_the_postcode(): void
    {
        $f = $this->formatter('street_city_zip');

        $this->assertSame(['10 Downing St', 'London SW1A 2AA'], $f->addressLines(self::LONDON));
    }

    /**
     * Ein US-Betrieb, der den Bundesstaat nicht ausfüllt, darf kein hängendes
     * Komma bekommen: "Minneapolis, 55401" ist falsch, "Minneapolis 55401"
     * ist eine unvollständige, aber richtige Adresse.
     */
    public function test_us_pattern_without_a_state_produces_no_dangling_comma(): void
    {
        $f = $this->formatter('street_city_state_zip');

        $lines = $f->addressLines([
            'street' => '400 S 4th St',
            'zip' => '55401',
            'city' => 'Minneapolis',
            'state' => '',
        ]);

        $this->assertSame(['400 S 4th St', 'Minneapolis 55401'], $lines);
    }

    public function test_a_missing_street_drops_its_line_entirely(): void
    {
        $f = $this->formatter('street_zip_city');

        $this->assertSame(['94032 Passau'], $f->addressLines(['zip' => '94032', 'city' => 'Passau']));
        $this->assertSame('94032 Passau', $f->address(['zip' => '94032', 'city' => 'Passau']));
    }

    /**
     * Kein Platzhalter: die Views entscheiden mit ihrem eigenen @if, ob sie
     * etwas zeigen. Ein "—" in einer PDF-Kopfzeile wäre schlechter als nichts.
     */
    public function test_an_empty_address_is_empty_not_a_placeholder(): void
    {
        $f = $this->formatter('street_zip_city');

        $this->assertSame([], $f->addressLines([]));
        $this->assertSame('', $f->address([]));
        $this->assertSame('', $f->address(null));
    }

    public function test_a_model_can_be_passed_directly(): void
    {
        $this->formatter('street_city_state_zip');

        $customer = Customer::create(['name' => 'Summit Snow']);

        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Parking lot',
            'street' => '400 S 4th St',
            'zip' => '55401',
            'city' => 'Minneapolis',
            'state' => 'MN',
        ]);

        $this->assertSame('400 S 4th St, Minneapolis, MN 55401', format_address($object));
        $this->assertSame(['400 S 4th St', 'Minneapolis, MN 55401'], format_address_lines($object));
    }

    public function test_the_helper_survives_a_null_relation(): void
    {
        $this->formatter('street_zip_city');

        // $job->customerObject kann null sein — die Views rufen den Helfer
        // trotzdem, statt jede Stelle mit einem ?-> zu spicken.
        $this->assertSame('', format_address(null));
        $this->assertSame([], format_address_lines(null));
    }
}
