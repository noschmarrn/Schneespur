<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A manual job may only be booked onto an actual driver (M018/S05, vuln-0002).
 *
 * The rule used to read users.role — the legacy column M014 kept as a bridge —
 * while the driver dropdown next to it is filled from User::drivers(), which
 * reads the role_user pivot. Two sources for one statement is how the finding
 * got through: whoever the pivot forgets, the column can still wave past.
 */
class ManualJobDriverValidationTest extends TestCase
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
     * The project has NO model factories (`database/factories/` does not exist).
     * The suite creates rows directly — follow that.
     */
    private function makeUser(string $name, string $email, ?string $role = null): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user->fresh();
    }

    private function admin(): User
    {
        return $this->makeUser('Admin', 'admin@test.local', 'admin');
    }

    private function object(): CustomerObject
    {
        $customer = Customer::create(['name' => 'Test Customer']);

        return CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Test Object',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(int $userId, CustomerObject $object): array
    {
        return [
            'user_id' => $userId,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => now()->subHours(3)->format('Y-m-d\TH:i'),
            'ended_at' => now()->subHours(2)->format('Y-m-d\TH:i'),
            'notes' => 'nondriver-user',
        ];
    }

    /** The attack from vuln-0002: someone else's user_id in the POST body. */
    public function test_manual_job_rejects_a_non_driver_user_id(): void
    {
        $nonDriver = $this->makeUser('Not A Driver', 'other-admin@test.local', 'admin');

        $this->actingAs($this->admin())
            ->post('/admin/jobs/manual', $this->payload($nonDriver->id, $this->object()))
            ->assertSessionHasErrors('user_id');
    }

    /**
     * The legacy column alone must no longer count as proof.
     *
     * No assignRole() here: users.role carries a DEFAULT of 'driver', so this
     * user already reads as a driver in the legacy column without anyone ever
     * granting it — which is precisely what the old rule accepted. Note the
     * write below is deliberately a no-op re-statement of that default; setting
     * the column to a DIFFERENT value would trip the legacy bridge in
     * User::booted(), which syncs a changed role into the pivot and would hand
     * this user the very role the test is trying to withhold.
     */
    public function test_manual_job_rejects_a_user_with_only_the_legacy_role_column(): void
    {
        $ghost = $this->makeUser('Ghost Driver', 'ghost@test.local');
        $ghost->forceFill(['role' => 'driver'])->save();

        $this->actingAs($this->admin())
            ->post('/admin/jobs/manual', $this->payload($ghost->id, $this->object()))
            ->assertSessionHasErrors('user_id');
    }

    /** A real driver still gets through. */
    public function test_manual_job_accepts_a_real_driver(): void
    {
        $driver = $this->makeUser('Real Driver', 'driver@test.local', 'driver');

        $this->actingAs($this->admin())
            ->post('/admin/jobs/manual', $this->payload($driver->id, $this->object()))
            ->assertSessionHasNoErrors();
    }
}
