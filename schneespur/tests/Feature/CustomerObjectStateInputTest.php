<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Companion to PlowThresholdInputTest: the object form's own address block.
 * The customer_objects.state column (Task 3) had no input anywhere — this
 * closes that gap and proves the geocode endpoint's state pass-through
 * (built in Task 5 for CustomerController::geocode()) actually fires from
 * the form that calls it.
 */
class CustomerObjectStateInputTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));

        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('admin');

        return $user->fresh();
    }

    public function test_a_state_is_saved_when_creating_an_object(): void
    {
        $customer = Customer::create(['name' => 'Summit Snow Customer']);

        $this->actingAs($this->admin())
            ->post("/admin/customers/{$customer->id}/objects", [
                'name' => 'Parking lot',
                'state' => 'MN',
                'notify_recipients' => 'customer',
            ])
            ->assertSessionHasNoErrors();

        $object = CustomerObject::where('name', 'Parking lot')->firstOrFail();

        $this->assertSame('MN', $object->state);
    }

    public function test_updating_an_objects_state_persists_the_change(): void
    {
        $customer = Customer::create(['name' => 'Kunde']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Hofeinfahrt',
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/customers/{$customer->id}/objects/{$object->id}", [
                'name' => 'Hofeinfahrt',
                'state' => 'BY',
                'notify_recipients' => 'customer',
            ])
            ->assertSessionHasNoErrors();

        $object->refresh();

        $this->assertSame('BY', $object->state);
    }

    /**
     * This guards the controller side only (CustomerController::geocode()
     * validating and forwarding a posted `state`) — it does NOT prove the
     * form actually sends one. That half is covered separately below by
     * rendering the form and inspecting its markup and inline script;
     * this test alone would still pass if the JavaScript never sent
     * `state` at all.
     */
    public function test_the_geocode_endpoint_receives_and_forwards_the_state(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '44.9778', 'lon' => '-93.2650'],
            ], 200),
        ]);

        $this->actingAs($this->admin())
            ->postJson('/admin/customers/geocode', [
                'street' => '400 S 4th St',
                'zip' => '55401',
                'city' => 'Minneapolis',
                'state' => 'MN',
            ])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'state=MN'));
    }

    public function test_the_create_form_renders_a_state_input_with_its_label(): void
    {
        $customer = Customer::create(['name' => 'Kunde']);

        $response = $this->actingAs($this->admin())
            ->get("/admin/customers/{$customer->id}/objects/create")
            ->assertOk();

        // Specific enough to fail if the field were removed or renamed —
        // not just "the word state appears somewhere on the page".
        $response->assertSee('id="state"', false);
        $response->assertSee('name="state"', false);
        $response->assertSee(__('customer_object.field_state'), false);
    }

    public function test_the_edit_form_renders_a_state_input_with_its_label(): void
    {
        $customer = Customer::create(['name' => 'Kunde']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Hofeinfahrt',
        ]);

        $response = $this->actingAs($this->admin())
            ->get("/admin/customers/{$customer->id}/objects/{$object->id}/edit")
            ->assertOk();

        $response->assertSee('id="state"', false);
        $response->assertSee('name="state"', false);
        $response->assertSee(__('customer_object.field_state'), false);
    }

    /**
     * The rendered inline script builds the geocode request body as
     * `JSON.stringify({ street, zip, city, state })`. Matching the whole
     * line verbatim would break on ordinary reformatting (spacing,
     * trailing comma, line wrap), so this pulls out the object literal
     * passed to JSON.stringify(...) with a regex and asserts `state` is
     * one of its keys, alongside street/zip/city — durable against
     * reformatting, but red the moment `state` is dropped from the body.
     */
    public function test_the_create_forms_geocode_request_body_includes_the_state_key(): void
    {
        $customer = Customer::create(['name' => 'Kunde']);

        $response = $this->actingAs($this->admin())
            ->get("/admin/customers/{$customer->id}/objects/create")
            ->assertOk();

        $matched = preg_match(
            '/JSON\.stringify\(\{\s*([^}]*?)\s*\}\)/',
            $response->getContent(),
            $matches
        );

        $this->assertSame(1, $matched, 'Expected a JSON.stringify({ ... }) call in the rendered form.');

        $keys = array_map('trim', explode(',', $matches[1]));

        $this->assertContains('street', $keys);
        $this->assertContains('zip', $keys);
        $this->assertContains('city', $keys);
        $this->assertContains('state', $keys);
    }
}
