<?php

namespace Tests\Feature;

use App\Enums\WeatherMoment;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\WeatherSnapshot;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Eine nie gemessene Schneehöhe muß als Platzhalter erscheinen, nicht als
 * „0,0 cm". Der Unterschied ist der zwischen „wir wissen es nicht" und
 * „es lag kein Schnee" — und das zweite ist in einem Einsatznachweis eine
 * Aussage, für die der Betreiber geradesteht.
 */
class WeatherSnowDepthDisplayTest extends TestCase
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

    private function jobWithSnowDepth(?float $snowDepth): Job
    {
        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create(['name' => 'Test Customer']);

        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Test Object',
        ]);

        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => '2026-01-15 08:00:00',
            'ended_at' => '2026-01-15 09:00:00',
        ]);

        $job = Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => '2026-01-15 08:00:00',
            'ended_at' => '2026-01-15 09:00:00',
        ]);

        WeatherSnapshot::create([
            'job_id' => $job->id,
            'moment' => WeatherMoment::End,
            'provider' => 'brightsky',
            'temperature' => -3.2,
            'precipitation' => 0.4,
            'snow_depth' => $snowDepth,
            'weather_code' => 71,
            'fetched_at' => '2026-01-15 09:00:00',
        ]);

        return $job;
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');

        return $admin->fresh();
    }

    public function test_the_admin_job_page_shows_the_placeholder_for_a_never_measured_depth(): void
    {
        app()->setLocale('de');
        $job = $this->jobWithSnowDepth(null);

        $this->actingAs($this->admin())
            ->get(route('admin.jobs.show', $job))
            ->assertOk()
            ->assertSee(format_snow_depth(null))
            ->assertDontSee(format_snow_depth(0.0));
    }

    /**
     * Die Gegenprobe zur Task-7-Entscheidung: eine gemessene Null bleibt
     * sichtbar. Beim Streudienst ist die Abwesenheit von Schnee selbst ein
     * Nachweis.
     */
    public function test_the_admin_job_page_still_shows_a_measured_zero(): void
    {
        app()->setLocale('de');
        $job = $this->jobWithSnowDepth(0.0);

        $this->actingAs($this->admin())
            ->get(route('admin.jobs.show', $job))
            ->assertOk()
            ->assertSee(format_snow_depth(0.0));
    }
}
