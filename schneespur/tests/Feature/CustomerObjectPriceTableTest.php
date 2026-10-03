<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\CustomerObjectPrice;
use App\Services\Extension\JobTypeRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\CreatesFormatFixtures;
use Tests\TestCase;

class CustomerObjectPriceTableTest extends TestCase
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
        return array_merge(['name' => 'Wohnanlage', 'notify_recipients' => 'customer'], $overrides);
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => 'Hausverwaltung']);
    }

    public function test_the_base_price_accepts_per_hour(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->admin())
            ->post("/admin/customers/{$customer->id}/objects", $this->payload([
                'price_amount' => '60,00', 'price_unit' => 'per_hour',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('per_hour', $customer->objects()->first()->price_unit);
    }

    public function test_a_price_per_job_type_is_stored_as_a_row(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->admin())
            ->post("/admin/customers/{$customer->id}/objects", $this->payload([
                'prices' => [
                    'streuen' => ['amount' => '40,00', 'unit' => 'per_job'],
                    'raumen' => ['amount' => '', 'unit' => ''],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $object = $customer->objects()->firstOrFail();

        $this->assertSame(1, $object->prices()->count());
        $row = $object->prices()->first();
        $this->assertSame('streuen', $row->job_type);
        $this->assertSame(4000, $row->amount_cents);
        $this->assertSame('per_job', $row->unit);
    }

    public function test_clearing_the_amount_removes_the_row(): void
    {
        $customer = $this->customer();
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'Wohnanlage']);
        CustomerObjectPrice::create([
            'customer_object_id' => $object->id, 'job_type' => 'streuen', 'amount_cents' => 4000, 'unit' => 'per_job',
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/customers/{$customer->id}/objects/{$object->id}", $this->payload([
                'prices' => ['streuen' => ['amount' => '', 'unit' => 'per_job']],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $object->prices()->count());
    }

    public function test_an_amount_without_a_unit_is_rejected(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->admin())
            ->post("/admin/customers/{$customer->id}/objects", $this->payload([
                'prices' => ['streuen' => ['amount' => '40,00', 'unit' => '']],
            ]))
            ->assertSessionHasErrors('prices.streuen.unit');

        $this->assertSame(0, CustomerObjectPrice::count());
    }

    public function test_an_unreadable_amount_is_rejected_rather_than_stored_as_zero(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->admin())
            ->post("/admin/customers/{$customer->id}/objects", $this->payload([
                'prices' => ['streuen' => ['amount' => 'vierzig', 'unit' => 'per_job']],
            ]))
            ->assertSessionHasErrors('prices.streuen.amount');
    }

    public function test_a_job_type_nobody_registered_is_rejected(): void
    {
        $customer = $this->customer();

        $this->actingAs($this->admin())
            ->post("/admin/customers/{$customer->id}/objects", $this->payload([
                'prices' => ['erfunden' => ['amount' => '40,00', 'unit' => 'per_job']],
            ]))
            ->assertSessionHasErrors('prices');
    }

    public function test_the_form_renders_one_row_per_registered_job_type_including_a_modules(): void
    {
        app(JobTypeRegistry::class)->registerType('gruenpflege', 'job.type_raumen', 500, 'testmodul');

        $customer = $this->customer();
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'Wohnanlage']);

        $response = $this->actingAs($this->admin())
            ->get("/admin/customers/{$customer->id}/objects/{$object->id}/edit");

        $response->assertOk();
        foreach (['raumen', 'streuen', 'kontrolle', 'raumen_streuen', 'gruenpflege'] as $type) {
            $response->assertSee('name="prices['.$type.'][amount]"', false);
        }
        $response->assertSee('value="per_hour"', false);
    }

    /**
     * Die Zeile gehört zu einer Einsatzart, deren Modul nicht mehr geladen
     * ist. Sie wird angezeigt, und ein Speichern ohne Änderung läßt sie
     * stehen — sonst verlöre der Betrieb beim Abschalten eines Moduls still
     * seine Preise.
     */
    public function test_an_orphaned_row_is_shown_and_survives_an_unchanged_save(): void
    {
        $customer = $this->customer();
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'Wohnanlage']);
        CustomerObjectPrice::create([
            'customer_object_id' => $object->id, 'job_type' => 'gruenpflege', 'amount_cents' => 5500, 'unit' => 'per_job',
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get("/admin/customers/{$customer->id}/objects/{$object->id}/edit")
            ->assertSee('name="prices[gruenpflege][amount]"', false)
            ->assertSee(__('customer_object.price_row_orphaned'));

        $this->actingAs($admin)
            ->put("/admin/customers/{$customer->id}/objects/{$object->id}", $this->payload([
                'prices' => ['gruenpflege' => ['amount' => '55,00', 'unit' => 'per_job']],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(5500, $object->prices()->where('job_type', 'gruenpflege')->value('amount_cents'));
    }

    public function test_a_save_that_sends_no_price_rows_touches_none(): void
    {
        $customer = $this->customer();
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'Wohnanlage']);
        CustomerObjectPrice::create([
            'customer_object_id' => $object->id, 'job_type' => 'streuen', 'amount_cents' => 4000, 'unit' => 'per_job',
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/customers/{$customer->id}/objects/{$object->id}", $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $object->prices()->count());
    }
}
