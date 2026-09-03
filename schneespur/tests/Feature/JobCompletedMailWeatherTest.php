<?php

namespace Tests\Feature;

use App\Enums\WeatherMoment;
use App\Mail\JobCompletedMail;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\WeatherSnapshot;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JobCompletedMailWeatherTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_completion_mail_names_the_snow_depth(): void
    {
        $job = $this->jobWithWeatherSnapshot(15.0);

        $rendered = (new JobCompletedMail($job->fresh(), true))->render();

        $this->assertStringContainsString(format_snow_depth(15.0), $rendered);
    }

    /**
     * The absence of snow is as much a matter of record as its presence on
     * a gritting run — the value must still appear when it is zero. A future
     * "tidy-up" wrapping the Blade line in `@if($weather->snow_depth)` would
     * silently hide exactly this case; this test exists to catch that.
     */
    public function test_the_completion_mail_names_a_zero_snow_depth(): void
    {
        $job = $this->jobWithWeatherSnapshot(0.0);

        $rendered = (new JobCompletedMail($job->fresh(), true))->render();

        $this->assertStringContainsString(format_snow_depth(0.0), $rendered);
    }

    /**
     * Und der Fall, den es vorher gar nicht geben konnte: nie gemessen.
     * Bright Sky (DWD) und MET Norway melden keine Schneehöhe; sie schrieben
     * dafür 0.0, und der Kunde eines deutschen Bright-Sky-Betreibers las in
     * jedem Nachweis „0,0 cm Schnee", auch mitten im Schneetreiben. Jetzt
     * steht dort der Platzhalter — bei einem Nachweis ist leer besser als
     * still falsch.
     */
    public function test_a_snow_depth_that_was_never_measured_shows_the_placeholder(): void
    {
        $job = $this->jobWithWeatherSnapshot(null);

        $rendered = (new JobCompletedMail($job->fresh(), true))->render();

        $this->assertStringContainsString(format_snow_depth(null), $rendered);
        $this->assertStringNotContainsString(format_snow_depth(0.0), $rendered);
    }

    private function jobWithWeatherSnapshot(?float $snowDepth): Job
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
            'provider' => 'openmeteo_free',
            'temperature' => -3.2,
            'precipitation' => 0.4,
            'snow_depth' => $snowDepth,
            'weather_code' => 71,
            'fetched_at' => '2026-01-15 09:00:00',
        ]);

        return $job;
    }
}
