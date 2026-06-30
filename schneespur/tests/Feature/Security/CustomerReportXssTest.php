<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerReportXssTest extends TestCase
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

    public function test_malicious_customer_name_is_escaped_in_report_email_modal(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $admin->role = UserRole::Admin;
        $admin->save();

        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $driver->role = UserRole::Driver;
        $driver->save();

        // A customer name is free text (string|max:200, no HTML sanitization).
        $payload = '<script>alert(document.cookie)</script>';

        // The email-confirm modal only renders when the customer has an email.
        $customer = Customer::create([
            'name' => $payload,
            'email' => 'victim@test.local',
        ]);

        // The modal lives in the @elseif($totalJobs > 0) branch, so the customer
        // needs at least one job in the current month for the path to render.
        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Objekt 1',
        ]);

        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => Carbon::now()->subHour(),
            'ended_at' => Carbon::now(),
        ]);

        Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => Carbon::now()->subHour(),
            'ended_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($admin->fresh())
            ->get('/admin/overview/customer-report?customer='.$customer->id);

        $response->assertOk();

        // Sanity: the vulnerable modal is actually on the page.
        $response->assertSee('send-report-email', false);

        // The raw script payload must never reach the rendered HTML.
        $response->assertDontSee($payload, false);

        // It must appear HTML-escaped instead.
        $response->assertSee('&lt;script&gt;alert(document.cookie)&lt;/script&gt;', false);
    }
}
