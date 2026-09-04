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
}
