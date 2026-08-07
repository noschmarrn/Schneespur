<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Format\Formatter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FormatterMoneyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function formatter(array $settings): Formatter
    {
        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }

        app()->forgetInstance(Formatter::class);

        return app(Formatter::class);
    }

    private function german(): Formatter
    {
        return $this->formatter([
            'number_format' => 'dot_comma',
            'currency_code' => 'EUR',
            'currency_position' => 'after',
        ]);
    }

    private function american(): Formatter
    {
        return $this->formatter([
            'number_format' => 'comma_dot',
            'currency_code' => 'USD',
            'currency_position' => 'before',
        ]);
    }

    public function test_money_in_germany(): void
    {
        $this->assertSame('1.234,56 €', $this->german()->money(123456));
    }

    public function test_money_in_the_united_states(): void
    {
        $this->assertSame('$1,234.56', $this->american()->money(123456));
    }

    public function test_swiss_numbers_use_an_apostrophe(): void
    {
        $f = $this->formatter([
            'number_format' => 'apostrophe_dot',
            'currency_code' => 'CHF',
            'currency_position' => 'before',
        ]);

        $this->assertSame('CHF 1\'234.56', $f->money(123456));
    }

    /** Switching currency relabels; it never converts. */
    public function test_switching_currency_keeps_the_amount(): void
    {
        $this->assertSame('100,00 €', $this->german()->money(10000));
        $this->assertSame('$100.00', $this->american()->money(10000));
    }

    public function test_an_unknown_currency_falls_back_to_its_code(): void
    {
        $f = $this->formatter([
            'number_format' => 'comma_dot',
            'currency_code' => 'NOK',
            'currency_position' => 'after',
        ]);

        $this->assertSame('50.00 NOK', $f->money(5000));
    }

    public function test_null_money_yields_the_placeholder(): void
    {
        $this->assertSame('—', $this->german()->money(null));
        $this->assertSame('—', $this->german()->number(null));
    }

    public function test_numbers_honour_the_decimal_count(): void
    {
        $this->assertSame('12,5', $this->german()->number(12.54, 1));
        $this->assertSame('12.54', $this->american()->number(12.54, 2));
    }

    public function test_parsing_accepts_what_the_operator_types(): void
    {
        $this->assertSame(1234.56, $this->german()->parseDecimal('1.234,56'));
        $this->assertSame(1234.56, $this->american()->parseDecimal('1,234.56'));
        $this->assertSame(1234.0, $this->german()->parseDecimal('1234'));
    }

    public function test_parsing_rejects_nonsense_instead_of_guessing(): void
    {
        $this->assertNull($this->german()->parseDecimal('abc'));
        $this->assertNull($this->german()->parseDecimal(''));
        $this->assertNull($this->german()->parseDecimal('12,34,56'));
    }

    /** The round trip is what the price form depends on. */
    public function test_money_survives_a_round_trip_in_both_formats(): void
    {
        foreach ([$this->german(), $this->american()] as $f) {
            $printed = $f->money(123456);
            $stripped = preg_replace('/[^\d.,\'\x{00A0} ]/u', '', $printed);

            $this->assertSame(123456, $f->parseMoneyToCents(trim($stripped)));
        }
    }

    public function test_the_bare_currency_symbol_is_available_for_labels(): void
    {
        $this->assertSame('€', $this->german()->currencySymbol());
        $this->assertSame('$', $this->american()->currencySymbol());
    }
}
