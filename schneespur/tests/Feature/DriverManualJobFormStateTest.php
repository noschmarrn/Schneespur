<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Driver-facing counterpart of ManualJobFormStateTest: the driver form was a
 * word-for-word copy of the admin form from before its fix (d75934d), so it
 * carried the same bug — after a validation error, the customer/object
 * selection dropped out of the form even though customer_object_id itself
 * was still in old().
 */
class DriverManualJobFormStateTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Web routes sit behind RedirectToInstaller.
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

    private function driver(): User
    {
        $user = User::create([
            'name' => 'Real Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('driver');
        $user->dsgvo_informed_at = now();
        $user->confirmed_version = 99;
        $user->save();

        return $user->fresh();
    }

    public function test_the_customer_selection_survives_a_validation_error(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung Donaublick', 'email' => 'hv@test.local']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Wohnanlage Ludwigsplatz',
        ]);

        $driver = $this->driver();

        // Deliberately incomplete: customer_object_id and type are both sent
        // (so the selection actually lands in old() and no unrelated field
        // fails), but started_at is left out — the one and only required
        // field missing besides those — so validation fails on that field
        // alone and the form comes back.
        $response = $this->actingAs($driver)
            ->from(route('driver.job.manual.create'))
            ->post(route('driver.job.manual.store'), [
                'customer_object_id' => $object->id,
                'type' => 'raumen_streuen',
                'ended_at' => now()->format('Y-m-d\TH:i'),
            ]);

        $response->assertRedirect(route('driver.job.manual.create'));
        $response->assertSessionHasErrors('started_at');
        $response->assertSessionDoesntHaveErrors('customer_object_id');
        $response->assertSessionDoesntHaveErrors('type');
        $response->assertSessionDoesntHaveErrors('ended_at');

        $followUp = $this->actingAs($driver)->get(route('driver.job.manual.create'));

        $followUp->assertOk();
        // The customer must come back preselected, derived from the object.
        $followUp->assertSee('selectedCustomerId: '.$customer->id, false);
        $followUp->assertSee('selectedObjectId: '.$object->id, false);
    }
}
