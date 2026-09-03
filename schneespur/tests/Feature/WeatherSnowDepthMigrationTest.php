<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WeatherSnowDepthMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migration(): object
    {
        return require base_path('database/migrations/2026_09_03_100000_convert_snow_depth_to_centimetres.php');
    }

    private function job(): Job
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
            'started_at' => '2026-08-06 08:00:00',
            'ended_at' => '2026-08-06 10:00:00',
        ]);

        return Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => '2026-08-06 08:00:00',
            'ended_at' => '2026-08-06 10:00:00',
        ]);
    }

    private function snapshot(string $provider, float $snowDepth): int
    {
        return DB::table('weather_snapshots')->insertGetId([
            'job_id' => $this->job()->id,
            'moment' => 'start',
            'provider' => $provider,
            'snow_depth' => $snowDepth,
            'fetched_at' => '2026-01-15 08:00:00',
            'created_at' => '2026-01-15 08:00:00',
            'updated_at' => '2026-01-15 08:00:00',
        ]);
    }

    public function test_open_meteo_rows_are_multiplied_by_one_hundred(): void
    {
        $id = $this->snapshot('openmeteo_free', 0.15);

        $this->migration()->up();

        $this->assertEqualsWithDelta(
            15.0,
            (float) DB::table('weather_snapshots')->where('id', $id)->value('snow_depth'),
            0.001,
        );
    }

    public function test_rows_from_other_providers_are_left_alone(): void
    {
        $id = $this->snapshot('met_norway', 0.0);

        $this->migration()->up();

        $this->assertEqualsWithDelta(
            0.0,
            (float) DB::table('weather_snapshots')->where('id', $id)->value('snow_depth'),
            0.001,
        );
    }

    public function test_the_correction_is_reversible(): void
    {
        $id = $this->snapshot('openmeteo_api', 0.15);

        $this->migration()->up();
        $this->migration()->down();

        $this->assertEqualsWithDelta(
            0.15,
            (float) DB::table('weather_snapshots')->where('id', $id)->value('snow_depth'),
            0.001,
        );
    }
}
