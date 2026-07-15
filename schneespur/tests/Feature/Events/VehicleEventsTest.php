<?php

namespace Tests\Feature\Events;

use App\Enums\UserRole;
use App\Events\Vehicle\VehicleCreated;
use App\Events\Vehicle\VehicleDeleted;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VehicleEventsTest extends TestCase
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

    private function createAdmin(): User
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => Hash::make('password')]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user->fresh();
    }

    public function test_store_dispatches_vehicle_created(): void
    {
        Event::fake([VehicleCreated::class]);
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('admin.vehicles.store'), ['name' => 'Truck 1'])
            ->assertRedirect();

        Event::assertDispatched(VehicleCreated::class, fn (VehicleCreated $e) => $e->vehicle->name === 'Truck 1');
    }

    public function test_destroy_dispatches_vehicle_deleted(): void
    {
        Event::fake([VehicleDeleted::class]);
        $admin = $this->createAdmin();
        $vehicle = Vehicle::create(['name' => 'Truck 2']);

        $this->actingAs($admin)
            ->delete(route('admin.vehicles.destroy', $vehicle))
            ->assertRedirect();

        Event::assertDispatched(VehicleDeleted::class, fn (VehicleDeleted $e) => $e->vehicle->name === 'Truck 2');
    }
}
