<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Support\Facades\Hash;

/**
 * Shared fixture builders for format-related feature tests.
 *
 * This project has no database/factories/ (Model::factory() always fails
 * here) — rows are built with Model::create(), which is exactly why this
 * trait exists: FormattedOutputTest and later format-related feature tests
 * (task 8) share the same admin/job scaffolding instead of copy-pasting it.
 */
trait CreatesFormatFixtures
{
    protected function setUpFormatFixtures(): void
    {
        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
    }

    protected function tearDownFormatFixtures(): void
    {
        @unlink(storage_path('app/installed.lock'));
    }

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function job(User $driver): Job
    {
        $customer = Customer::create(['name' => 'Test Customer']);

        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Test Object',
            'price_amount_cents' => 123456,
            'price_unit' => 'per_job',
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
}
