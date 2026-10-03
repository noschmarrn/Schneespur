<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\CreatesFormatFixtures;
use Tests\TestCase;

/**
 * Das Sammel-PDF setzte als Kundenadresse die des ersten Objekts ein. Bei
 * einer Hausverwaltung — dem typischen Winterdienstkunden — ist das die
 * Adresse irgendeiner Wohnanlage, nicht die der Verwaltung.
 */
class CustomerAddressTest extends TestCase
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

    private function object(Customer $customer, string $street): CustomerObject
    {
        return CustomerObject::create([
            'customer_id' => $customer->id, 'name' => $street, 'street' => $street, 'zip' => '94032', 'city' => 'Passau',
        ]);
    }

    private function renderReport(Customer $customer): string
    {
        return view('pdf.customer-report', [
            'customer' => $customer->load('objects'),
            'jobs' => collect(),
            'jobData' => [],
            'from' => Carbon::parse('2026-01-01'),
            'to' => Carbon::parse('2026-01-31'),
            'coverData' => [],
        ])->render();
    }

    public function test_the_own_address_wins(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung', 'street' => 'Ludwigsplatz 1', 'zip' => '94032', 'city' => 'Passau']);
        $this->object($customer, 'Innstraße 12');

        $this->assertTrue($customer->hasOwnAddress());
        $this->assertTrue($customer->documentAddressSource()->is($customer));
    }

    public function test_a_single_object_stands_in_when_the_customer_has_no_address(): void
    {
        $customer = Customer::create(['name' => 'Autohaus']);
        $object = $this->object($customer, 'Regensburger Straße 5');

        $this->assertFalse($customer->hasOwnAddress());
        $this->assertTrue($customer->documentAddressSource()->is($object));
    }

    public function test_several_objects_and_no_own_address_mean_no_address_at_all(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        $this->object($customer, 'Innstraße 12');
        $this->object($customer, 'Donaulände 3');

        $this->assertNull($customer->documentAddressSource());
    }

    public function test_a_customer_without_objects_and_without_address_has_none(): void
    {
        $this->assertNull(Customer::create(['name' => 'Neu'])->documentAddressSource());
    }

    public function test_the_collective_report_prints_the_customers_own_address(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung', 'street' => 'Ludwigsplatz 1', 'zip' => '94032', 'city' => 'Passau']);
        $this->object($customer, 'Innstraße 12');
        $this->object($customer, 'Donaulände 3');

        $html = $this->renderReport($customer);

        $this->assertStringContainsString('Ludwigsplatz 1', $html);
        $this->assertStringNotContainsString('Innstraße 12', $html);
    }

    public function test_the_collective_report_prints_no_address_rather_than_a_wrong_one(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung']);
        $this->object($customer, 'Innstraße 12');
        $this->object($customer, 'Donaulände 3');

        $html = $this->renderReport($customer);

        $this->assertStringNotContainsString('Innstraße 12', $html);
        $this->assertStringNotContainsString(__('job.pdf_address'), $html);
    }

    public function test_the_form_stores_the_address_and_shows_it_again(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.customers.store'), [
                'name' => 'Summit Property Mgmt',
                'street' => '400 S 4th St',
                'zip' => '55401',
                'city' => 'Minneapolis',
                'state' => 'MN',
            ])
            ->assertSessionHasNoErrors();

        $customer = Customer::where('name', 'Summit Property Mgmt')->firstOrFail();
        $this->assertSame('MN', $customer->state);

        $this->actingAs($admin)
            ->get(route('admin.customers.edit', $customer))
            ->assertOk()
            ->assertSee('name="state"', false)
            ->assertSee('400 S 4th St');
    }

    public function test_the_customer_list_shows_the_own_address(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung', 'street' => 'Ludwigsplatz 1', 'zip' => '94032', 'city' => 'Passau']);
        $this->object($customer, 'Innstraße 12');
        $this->object($customer, 'Donaulände 3');

        $this->actingAs($this->admin())
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Ludwigsplatz 1')
            ->assertDontSee('Innstraße 12');
    }
}
