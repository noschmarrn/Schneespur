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
            'snow_depth' => 15.0,
            'weather_code' => 71,
            'fetched_at' => '2026-01-15 09:00:00',
        ]);

        $rendered = (new JobCompletedMail($job->fresh(), true))->render();

        $this->assertStringContainsString(format_snow_depth(15.0), $rendered);
    }
}
