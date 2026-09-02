<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every manually entered job stored before 1.2.1 sits in the database off by
 * the offset that applied on its own date. The migration puts them where they
 * were always meant to be.
 */
class ManualJobTimezoneMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * The migration is called directly rather than through artisan: the suite
     * has already migrated, so a re-run would do nothing at all.
     */
    private function migration(): object
    {
        return require database_path('migrations/2026_09_02_120000_shift_manual_jobs_to_local_time.php');
    }

    private function seedJob(?string $startedAt, ?string $endedAt, bool $manual): int
    {
        $suffix = uniqid();

        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Kunde', 'email' => "k{$suffix}@test.local",
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $objectId = DB::table('customer_objects')->insertGetId([
            'customer_id' => $customerId, 'name' => 'Objekt',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Fahrer', 'email' => "d{$suffix}@test.local", 'password' => bcrypt('secret'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $shiftId = DB::table('work_shifts')->insertGetId([
            'user_id' => $userId,
            'started_at' => $startedAt, 'ended_at' => $endedAt,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('service_jobs')->insertGetId([
            'work_shift_id' => $shiftId,
            'user_id' => $userId,
            'customer_id' => $customerId,
            'customer_object_id' => $objectId,
            'type' => 'raumen',
            'is_manual' => $manual,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function useZone(string $zone): void
    {
        Setting::set('display_timezone', $zone);
    }

    /** Winter: Berlin is UTC+1, so a job stored as 08:30 was meant as 08:30 local. */
    public function test_a_winter_job_moves_by_one_hour(): void
    {
        $this->useZone('Europe/Berlin');
        $id = $this->seedJob('2026-01-15 08:30:00', '2026-01-15 09:30:00', true);

        $this->migration()->up();

        $row = DB::table('service_jobs')->find($id);
        $this->assertStringStartsWith('2026-01-15 07:30', (string) $row->started_at);
        $this->assertStringStartsWith('2026-01-15 08:30', (string) $row->ended_at);
    }

    /**
     * Summer: two hours, same zone. A fixed offset passes the test above and
     * fails this one — which is the entire reason the shift is per record.
     */
    public function test_a_summer_job_moves_by_two_hours(): void
    {
        $this->useZone('Europe/Berlin');
        $id = $this->seedJob('2026-07-15 08:30:00', '2026-07-15 09:30:00', true);

        $this->migration()->up();

        $this->assertStringStartsWith(
            '2026-07-15 06:30',
            (string) DB::table('service_jobs')->find($id)->started_at
        );
    }

    /** GPS jobs were always UTC. Touching them would break data that is right. */
    public function test_automatic_jobs_are_left_alone(): void
    {
        $this->useZone('Europe/Berlin');
        $id = $this->seedJob('2026-01-15 08:30:00', '2026-01-15 09:30:00', false);

        $this->migration()->up();

        $this->assertStringStartsWith(
            '2026-01-15 08:30',
            (string) DB::table('service_jobs')->find($id)->started_at
        );
    }

    public function test_the_migration_reverses_itself(): void
    {
        $this->useZone('Europe/Berlin');
        $id = $this->seedJob('2026-01-15 08:30:00', '2026-01-15 09:30:00', true);

        $migration = $this->migration();
        $migration->up();
        $migration->down();

        $this->assertStringStartsWith(
            '2026-01-15 08:30',
            (string) DB::table('service_jobs')->find($id)->started_at
        );
    }

    /** A job whose shift was never closed must not throw, and must still be corrected. */
    public function test_a_job_without_an_end_survives(): void
    {
        $this->useZone('Europe/Berlin');
        $id = $this->seedJob('2026-01-15 08:30:00', null, true);

        $this->migration()->up();

        $row = DB::table('service_jobs')->find($id);
        $this->assertStringStartsWith('2026-01-15 07:30', (string) $row->started_at);
        $this->assertNull($row->ended_at);
    }

    /**
     * A zone behind UTC moves the other way — and across the day boundary,
     * which is where a wrong migration is worst.
     */
    public function test_a_zone_behind_utc_moves_the_other_way(): void
    {
        $this->useZone('America/Chicago');
        $id = $this->seedJob('2026-01-15 08:30:00', '2026-01-15 09:30:00', true);

        $this->migration()->up();

        $this->assertStringStartsWith(
            '2026-01-15 14:30',
            (string) DB::table('service_jobs')->find($id)->started_at
        );
    }
}
