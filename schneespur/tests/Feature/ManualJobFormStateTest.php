<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression test for the US practice-test finding: after a validation error
 * on the manual job form, the customer/object selection was lost while other
 * fields survived, so fixing one field earned the next error.
 *
 * The <select id="customer_id"> carries no name attribute on purpose — the
 * customer follows from the object, not from its own POST field. That meant
 * old('customer_id') was always empty, so the Alpine state that drives the
 * whole form (selectedCustomerId) always came back null, taking the
 * dependent object list with it even though customer_object_id itself was
 * still in old().
 */
class ManualJobFormStateTest extends TestCase
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

    /**
     * The project has NO model factories (`database/factories/` does not
     * exist) — create rows directly, and grant the role via the pivot
     * (assignRole), matching the rest of the manual-job test suite.
     */
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

    private function driver(): User
    {
        $user = User::create([
            'name' => 'Real Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('driver');

        return $user->fresh();
    }

    public function test_the_customer_selection_survives_a_validation_error(): void
    {
        $customer = Customer::create(['name' => 'Hausverwaltung Donaublick', 'email' => 'hv@test.local']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Wohnanlage Ludwigsplatz',
        ]);

        $admin = $this->admin();
        $driver = $this->driver();

        // Deliberately incomplete: user_id, customer_object_id and type are all
        // sent (so the selection actually lands in old() and no unrelated field
        // fails), but started_at is left out — the one and only required field
        // missing — so validation fails on that field alone and the form comes
        // back.
        $response = $this->actingAs($admin)
            ->from(route('admin.jobs.manual.create'))
            ->post(route('admin.jobs.manual.store'), [
                'user_id' => $driver->id,
                'customer_object_id' => $object->id,
                'type' => 'raumen_streuen',
                'ended_at' => now()->format('Y-m-d\TH:i'),
            ]);

        $response->assertRedirect(route('admin.jobs.manual.create'));
        $response->assertSessionHasErrors('started_at');
        $response->assertSessionDoesntHaveErrors('user_id');
        $response->assertSessionDoesntHaveErrors('customer_object_id');

        $followUp = $this->actingAs($admin)->get(route('admin.jobs.manual.create'));

        $followUp->assertOk();
        // The customer must come back preselected, derived from the object.
        $followUp->assertSee('selectedCustomerId: '.$customer->id, false);
        $followUp->assertSee('selectedObjectId: '.$object->id, false);
    }
}
