<?php

namespace Tests\Feature;

use App\Enums\WeatherMoment;
use App\Jobs\FetchWeather;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WeatherRetryTest extends TestCase
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

    public function test_the_retry_button_asks_for_the_moment_of_the_job_and_may_overwrite(): void
    {
        Queue::fake();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');
        $admin = $admin->fresh();

        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create(['name' => 'Test Customer']);

        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Test Object',
            'lat' => 48.57,
            'lon' => 13.46,
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
            'is_manual' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.jobs.weather-retry', [$job->id, 'start']))
            ->assertRedirect();

        Queue::assertPushed(FetchWeather::class, function (FetchWeather $queued) use ($job) {
            return $queued->jobId === $job->id
                && $queued->moment === WeatherMoment::Start
                && $queued->momentAt?->toDateTimeString() === '2026-01-15 08:00:00'
                && $queued->force === true;
        });
    }
}
