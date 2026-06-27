<?php

namespace Tests\Feature\Events;

use App\Enums\JobType;
use App\Enums\UserRole;
use App\Events\GpsPointReceived;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\GpsPoint;
use App\Models\User;
use App\Services\JobLifecycleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GpsPointReceivedTest extends TestCase
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

    private function createDriverWithOwntracks(string $username = 'driver-ot', string $password = 'secret'): User
    {
        $user = User::create([
            'name' => 'Driver User',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
            'owntracks_username' => $username,
        ]);
        $user->role = UserRole::Driver;
        // owntracks_password_hash is $hidden (not fillable); set it explicitly like prod does.
        $user->owntracks_password_hash = Hash::make($password);
        $user->save();

        return $user->fresh();
    }

    private function postOwntracks(array $payload, string $username = 'driver-ot', string $password = 'secret'): \Illuminate\Testing\TestResponse
    {
        return $this->call(
            'POST',
            '/api/owntracks',
            [],
            [],
            [],
            [
                'PHP_AUTH_USER' => $username,
                'PHP_AUTH_PW' => $password,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode($payload),
        );
    }

    private function postLocation(string $username, string $password, array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->postOwntracks(array_merge([
            '_type' => 'location',
            'lat' => 48.5667,
            'lon' => 13.4319,
            'tst' => 1719500000,
            'acc' => 12,
        ], $payload), $username, $password);
    }

    public function test_idle_ping_dispatches_event_without_creating_gps_point(): void
    {
        Event::fake([GpsPointReceived::class]);

        $driver = $this->createDriverWithOwntracks();

        $response = $this->postLocation('driver-ot', 'secret');

        $response->assertOk();

        // Idle behaviour preserved: no GpsPoint persisted when no active job.
        $this->assertSame(0, GpsPoint::count());

        Event::assertDispatched(GpsPointReceived::class, function (GpsPointReceived $e) use ($driver) {
            return $e->user->id === $driver->id
                && abs($e->lat - 48.5667) < 0.00001
                && abs($e->lon - 13.4319) < 0.00001
                && $e->timestamp === 1719500000
                && $e->accuracy === 12
                && $e->activeJob === null;
        });
    }

    public function test_active_ping_dispatches_event_and_creates_gps_point(): void
    {
        Event::fake([GpsPointReceived::class]);

        $driver = $this->createDriverWithOwntracks();

        $customer = Customer::create(['name' => 'Test Customer']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Test Object',
        ]);

        $service = app(JobLifecycleService::class);
        $service->startShift($driver);
        $job = $service->startJob($driver, $object, JobType::Raumen);

        $response = $this->postLocation('driver-ot', 'secret');

        $response->assertOk();

        $this->assertSame(1, GpsPoint::count());
        $this->assertSame($job->id, GpsPoint::first()->job_id);

        Event::assertDispatched(GpsPointReceived::class, function (GpsPointReceived $e) use ($driver, $job) {
            return $e->user->id === $driver->id && $e->activeJob?->id === $job->id;
        });
    }

    public function test_non_location_payload_dispatches_nothing(): void
    {
        Event::fake([GpsPointReceived::class]);

        $this->createDriverWithOwntracks();

        $response = $this->postOwntracks(['_type' => 'transition']);

        $response->assertOk();
        Event::assertNotDispatched(GpsPointReceived::class);
    }
}
