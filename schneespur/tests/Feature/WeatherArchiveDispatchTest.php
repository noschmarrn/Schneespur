<?php

namespace Tests\Feature;

use App\Enums\WeatherMoment;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\Setting;
use App\Models\User;
use App\Models\WeatherSnapshot;
use App\Models\WorkShift;
use App\Services\JobLifecycleService;
use App\Services\Weather\WeatherFetcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherArchiveDispatchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function job(string $startedAt, string $endedAt): Job
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
            'lat' => 48.57,
            'lon' => 13.46,
        ]);

        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);

        return Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'is_manual' => true,
        ]);
    }

    private function fakeArchive(): void
    {
        Http::fake([
            'archive-api.open-meteo.com/*' => Http::response([
                'hourly' => [
                    'time' => ['2026-01-15T08:00'],
                    'temperature_2m' => [-3.2],
                    'precipitation' => [0.4],
                    'snow_depth' => [0.15],
                    'weather_code' => [71],
                    'wind_speed_10m' => [12.0],
                    'relative_humidity_2m' => [92],
                ],
            ]),
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'time' => '2026-09-03T12:00',
                    'temperature_2m' => 24.7,
                    'precipitation' => 0.0,
                    'snow_depth' => 0.0,
                    'weather_code' => 3,
                    'wind_speed_10m' => 5.0,
                    'relative_humidity_2m' => 40,
                ],
            ]),
        ]);
    }

    public function test_a_past_moment_is_read_from_the_archive_not_from_today(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        $this->fakeArchive();

        $job = $this->job('2026-01-15 08:00:00', '2026-01-15 09:00:00');

        app(WeatherFetcher::class)->record(
            $job->id,
            WeatherMoment::Start,
            48.57,
            13.46,
            Carbon::parse('2026-01-15 08:00:00', 'UTC'),
        );

        $snapshot = WeatherSnapshot::where('job_id', $job->id)->first();

        $this->assertNotNull($snapshot);
        $this->assertEqualsWithDelta(-3.2, (float) $snapshot->temperature, 0.001);
        $this->assertSame(71, $snapshot->weather_code);
        $this->assertEqualsWithDelta(15.0, (float) $snapshot->snow_depth, 0.001);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.open-meteo.com/v1/forecast'));
    }

    public function test_a_moment_that_is_now_still_uses_the_current_reading(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        $this->fakeArchive();

        $job = $this->job('2026-09-03 11:55:00', '2026-09-03 12:00:00');

        app(WeatherFetcher::class)->record(
            $job->id,
            WeatherMoment::End,
            48.57,
            13.46,
            Carbon::parse('2026-09-03 12:00:00', 'UTC'),
        );

        $snapshot = WeatherSnapshot::where('job_id', $job->id)->first();

        $this->assertEqualsWithDelta(24.7, (float) $snapshot->temperature, 0.001);
    }

    public function test_a_provider_without_an_archive_leaves_the_weather_empty_rather_than_wrong(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        Setting::set('weather_provider', 'met_norway');
        Http::fake(['*' => Http::response([], 200)]);

        $job = $this->job('2026-01-15 08:00:00', '2026-01-15 09:00:00');

        $result = app(WeatherFetcher::class)->record(
            $job->id,
            WeatherMoment::Start,
            48.57,
            13.46,
            Carbon::parse('2026-01-15 08:00:00', 'UTC'),
        );

        $this->assertNull($result);
        $this->assertSame(0, WeatherSnapshot::where('job_id', $job->id)->count());
        Http::assertNothingSent();
    }

    public function test_an_existing_snapshot_is_kept_unless_the_caller_forces_it(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        $this->fakeArchive();

        $job = $this->job('2026-01-15 08:00:00', '2026-01-15 09:00:00');

        WeatherSnapshot::create([
            'job_id' => $job->id,
            'moment' => WeatherMoment::Start,
            'provider' => 'openmeteo_free',
            'temperature' => 99.0,
            'fetched_at' => now(),
        ]);

        $fetcher = app(WeatherFetcher::class);
        $at = Carbon::parse('2026-01-15 08:00:00', 'UTC');

        $this->assertNull($fetcher->record($job->id, WeatherMoment::Start, 48.57, 13.46, $at));
        $this->assertEqualsWithDelta(
            99.0,
            (float) WeatherSnapshot::where('job_id', $job->id)->value('temperature'),
            0.001,
        );

        $fetcher->record($job->id, WeatherMoment::Start, 48.57, 13.46, $at, force: true);

        $this->assertEqualsWithDelta(
            -3.2,
            (float) WeatherSnapshot::where('job_id', $job->id)->value('temperature'),
            0.001,
        );
    }

    /**
     * Der eigentliche Defekt, an der Stelle, an der er auftrat: ein im
     * September nacherfaßter Januar-Einsatz. Die vier Tests darüber prüfen
     * den Fetcher; dieser prüft, daß die Aufrufstelle ihm den Zeitpunkt des
     * Einsatzes auch wirklich mitgibt.
     */
    public function test_a_manually_recorded_job_is_stamped_with_the_weather_of_its_own_day(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        Event::fake();

        Http::fake([
            'archive-api.open-meteo.com/*' => Http::response([
                'hourly' => [
                    'time' => ['2026-01-15T08:00', '2026-01-15T09:00'],
                    'temperature_2m' => [-3.2, -2.8],
                    'precipitation' => [0.4, 0.1],
                    'snow_depth' => [0.15, 0.16],
                    'weather_code' => [71, 71],
                    'wind_speed_10m' => [12.0, 11.0],
                    'relative_humidity_2m' => [92, 90],
                ],
            ]),
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'time' => '2026-09-03T12:00',
                    'temperature_2m' => 24.7,
                    'precipitation' => 0.0,
                    'snow_depth' => 0.0,
                    'weather_code' => 3,
                    'wind_speed_10m' => 5.0,
                    'relative_humidity_2m' => 40,
                ],
            ]),
        ]);

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

        $job = app(JobLifecycleService::class)->createManualJob(
            $driver,
            $object,
            'raumen',
            Carbon::parse('2026-01-15 08:00:00', 'UTC'),
            Carbon::parse('2026-01-15 09:00:00', 'UTC'),
        );

        $start = WeatherSnapshot::where('job_id', $job->id)->where('moment', WeatherMoment::Start)->first();
        $end = WeatherSnapshot::where('job_id', $job->id)->where('moment', WeatherMoment::End)->first();

        $this->assertNotNull($start);
        $this->assertNotNull($end);
        $this->assertEqualsWithDelta(-3.2, (float) $start->temperature, 0.001);
        $this->assertEqualsWithDelta(-2.8, (float) $end->temperature, 0.001);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.open-meteo.com/v1/forecast'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
