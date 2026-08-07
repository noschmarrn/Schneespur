<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Services\Format\Formatter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\CreatesFormatFixtures;
use Tests\TestCase;

/**
 * StoreCustomerObjectRequest now parses the price field through
 * Formatter::parseMoneyToCents() instead of a naive comma-to-dot
 * str_replace(). parseMoneyToCents() returns null for input it cannot
 * read — this locks in that such input is REJECTED by validation rather
 * than silently persisted as a price of 0 (a data-integrity bug, not a
 * formatting one).
 */
class CustomerObjectPriceValidationTest extends TestCase
{
    use CreatesFormatFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpFormatFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownFormatFixtures();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Object',
            'notify_recipients' => 'customer',
        ], $overrides);
    }

    public function test_a_well_formed_price_is_stored_as_cents(): void
    {
        $admin = $this->admin();
        $customer = Customer::create(['name' => 'Test Customer']);

        $this->actingAs($admin)
            ->post("/admin/customers/{$customer->id}/objects", $this->payload([
                'price_amount' => '1234,56',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(123456, $customer->objects()->first()->price_amount_cents);
    }

    public function test_an_unparsable_price_is_rejected_not_stored_as_zero(): void
    {
        $admin = $this->admin();
        $customer = Customer::create(['name' => 'Test Customer']);

        $this->actingAs($admin)
            ->post("/admin/customers/{$customer->id}/objects", $this->payload([
                'price_amount' => 'not-a-price',
            ]))
            ->assertSessionHasErrors('price_amount');

        $this->assertNull($customer->objects()->first());
    }

    public function test_a_negative_price_is_rejected(): void
    {
        $admin = $this->admin();
        $customer = Customer::create(['name' => 'Test Customer']);

        $this->actingAs($admin)
            ->post("/admin/customers/{$customer->id}/objects", $this->payload([
                'price_amount' => '-5',
            ]))
            ->assertSessionHasErrors('price_amount');
    }

    public function test_an_empty_price_is_allowed_and_stored_as_null(): void
    {
        $admin = $this->admin();
        $customer = Customer::create(['name' => 'Test Customer']);

        $this->actingAs($admin)
            ->post("/admin/customers/{$customer->id}/objects", $this->payload([
                'price_amount' => '',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNull($customer->objects()->first()->price_amount_cents);
    }

    /** Parsing must honour the configured number format, not assume German. */
    public function test_a_us_formatted_price_is_parsed_under_us_settings(): void
    {
        foreach ([
            'currency_code' => 'USD',
            'currency_position' => 'before',
            'number_format' => 'comma_dot',
            'date_format' => 'mdy_slash',
            'time_format' => '12h',
            'unit_distance' => 'imperial',
            'unit_temperature' => 'fahrenheit',
            'unit_weight' => 'imperial',
        ] as $key => $value) {
            Setting::set($key, $value);
        }
        app()->forgetInstance(Formatter::class);

        $admin = $this->admin();
        $customer = Customer::create(['name' => 'Test Customer']);

        $this->actingAs($admin)
            ->post("/admin/customers/{$customer->id}/objects", $this->payload([
                'price_amount' => '1,234.56',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(123456, $customer->objects()->first()->price_amount_cents);

        app()->forgetInstance(Formatter::class);
    }
}
