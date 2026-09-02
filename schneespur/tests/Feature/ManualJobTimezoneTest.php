<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\Setting;
use App\Models\User;
use App\Support\Timezones;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A datetime-local field sends "2026-01-15T08:30" with no zone at all.
 * Carbon::parse() put config('app.timezone') on it — UTC — so an operator in
 * Berlin who typed 08:30 got 08:30 UTC stored and 09:30 shown back. Both field
 * test reports name this as a blocker.
 */
class ManualJobTimezoneTest extends TestCase
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

    /** Roles live in the pivot, not in users.role — assignRole, as the rest of the suite does. */
    private function admin(): User
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.local', 'password' => Hash::make('password')]);
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function driver(): User
    {
        $user = User::create(['name' => 'Fahrer', 'email' => 'driver@test.local', 'password' => Hash::make('password')]);
        $user->assignRole('driver');

        // Without this every driver route redirects to the privacy onboarding,
        // and the POST never reaches the controller — the response carries no
        // validation errors either, so the failure looks like "no job created"
        // rather than "never got there".
        $user->dsgvo_informed_at = now();
        $user->confirmed_version = 99;
        $user->save();

        return $user->fresh();
    }

    private function object(): CustomerObject
    {
        $customer = Customer::create(['name' => 'Hausverwaltung Donaublick', 'email' => 'hv@test.local']);

        return CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Wohnanlage Ludwigsplatz',
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function postAsAdmin(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())
            ->from(route('admin.jobs.manual.create'))
            ->post(route('admin.jobs.manual.store'), $payload);
    }

    private function useZone(string $zone): void
    {
        Setting::set('display_timezone', $zone);
        Timezones::apply();
    }

    /** Winter: Berlin is UTC+1, so 08:30 typed in belongs at 07:30 UTC. */
    public function test_input_is_read_as_local_time_in_winter(): void
    {
        $this->useZone('Europe/Berlin');

        $this->postAsAdmin([
            'user_id' => $this->driver()->id,
            'customer_object_id' => $this->object()->id,
            'type' => 'raumen',
            'started_at' => '2026-01-15T08:30',
            'ended_at' => '2026-01-15T09:30',
        ])->assertSessionDoesntHaveErrors();

        $job = Job::latest('id')->firstOrFail();

        $this->assertSame('2026-01-15 07:30:00', $job->started_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-15 08:30:00', $job->ended_at->utc()->format('Y-m-d H:i:s'));
    }

    /**
     * Summer, same zone, two hours instead of one. A fixed offset would pass
     * the winter test and fail this one — which is the whole reason the
     * conversion has to go through the zone rather than a number.
     */
    public function test_input_is_read_as_local_time_in_summer(): void
    {
        $this->useZone('Europe/Berlin');

        $this->postAsAdmin([
            'user_id' => $this->driver()->id,
            'customer_object_id' => $this->object()->id,
            'type' => 'raumen',
            'started_at' => '2026-07-15T08:30',
            'ended_at' => '2026-07-15T09:30',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(
            '2026-07-15 06:30:00',
            Job::latest('id')->firstOrFail()->started_at->utc()->format('Y-m-d H:i:s')
        );
    }

    /** A zone behind UTC rather than ahead — the case the American report was written from. */
    public function test_a_zone_behind_utc_shifts_the_other_way(): void
    {
        $this->useZone('America/Chicago');

        $this->postAsAdmin([
            'user_id' => $this->driver()->id,
            'customer_object_id' => $this->object()->id,
            'type' => 'raumen',
            'started_at' => '2026-01-15T08:30',
            'ended_at' => '2026-01-15T09:30',
        ])->assertSessionDoesntHaveErrors();

        // Chicago is UTC-6 in January: 08:30 local is 14:30 UTC.
        $this->assertSame(
            '2026-01-15 14:30:00',
            Job::latest('id')->firstOrFail()->started_at->utc()->format('Y-m-d H:i:s')
        );
    }

    /**
     * The design in one assertion: the column holds UTC whatever the
     * operator's zone. Read the raw row, not the cast attribute.
     */
    public function test_the_database_column_holds_utc(): void
    {
        $this->useZone('America/Chicago');

        $this->postAsAdmin([
            'user_id' => $this->driver()->id,
            'customer_object_id' => $this->object()->id,
            'type' => 'raumen',
            'started_at' => '2026-01-15T08:30',
            'ended_at' => '2026-01-15T09:30',
        ])->assertSessionDoesntHaveErrors();

        $raw = DB::table('service_jobs')->latest('id')->first();

        $this->assertStringStartsWith('2026-01-15 14:30', (string) $raw->started_at);
    }

    /**
     * The validation boundary has to move with the zone. Comparing a
     * local-time input against UTC "now" shifted the boundary by the offset —
     * in Chicago that let an operator book six hours into the future.
     */
    public function test_a_future_local_time_is_still_refused_in_a_zone_behind_utc(): void
    {
        $this->useZone('America/Chicago');

        $future = now()->setTimezone('America/Chicago')->addHours(2);

        $this->postAsAdmin([
            'user_id' => $this->driver()->id,
            'customer_object_id' => $this->object()->id,
            'type' => 'raumen',
            'started_at' => $future->format('Y-m-d\TH:i'),
            'ended_at' => $future->copy()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('started_at');
    }

    /** And the boundary must not overshoot the other way: a genuinely past time goes through. */
    public function test_a_recent_local_time_is_accepted(): void
    {
        $this->useZone('America/Chicago');

        $past = now()->setTimezone('America/Chicago')->subHours(3);

        $this->postAsAdmin([
            'user_id' => $this->driver()->id,
            'customer_object_id' => $this->object()->id,
            'type' => 'raumen',
            'started_at' => $past->format('Y-m-d\TH:i'),
            'ended_at' => $past->copy()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionDoesntHaveErrors(['started_at', 'ended_at']);
    }

    /**
     * The driver form is a separate FormRequest and was once a verbatim copy
     * of the admin one. It has to be fixed in its own right — the last copy
     * between these two is why a fix in Phase 0 missed the driver version.
     */
    public function test_the_driver_form_converts_the_same_way(): void
    {
        $this->useZone('Europe/Berlin');

        $driver = $this->driver();

        $this->actingAs($driver)
            ->from(route('driver.job.manual.create'))
            ->post(route('driver.job.manual.store'), [
                'customer_object_id' => $this->object()->id,
                'type' => 'raumen',
                'started_at' => '2026-01-15T08:30',
                'ended_at' => '2026-01-15T09:30',
            ])->assertSessionDoesntHaveErrors();

        $this->assertSame(
            '2026-01-15 07:30:00',
            Job::latest('id')->firstOrFail()->started_at->utc()->format('Y-m-d H:i:s')
        );
    }
}
