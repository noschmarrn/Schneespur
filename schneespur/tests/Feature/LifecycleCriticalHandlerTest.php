<?php

namespace Tests\Feature;

use App\Enums\LifecyclePoint;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Services\Extension\LifecycleFieldRegistry;
use App\Services\JobLifecycleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A handler that fails today is logged and skipped, and because its inner
 * DB::transaction is a savepoint inside JobLifecycleService's transaction, the
 * job survives without it. For the tour module that is the offline timestamp
 * being replayed: the job exists, but stamped with the time of the sync rather
 * than the time of the act — the evidence chain silently devalued.
 */
class LifecycleCriticalHandlerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function driver(): User
    {
        $user = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('driver');

        return $user->fresh();
    }

    private function object(): CustomerObject
    {
        $customer = Customer::create(['name' => 'Test Customer']);

        return CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Test Object',
        ]);
    }

    private function registerHandler(bool $critical): void
    {
        app(LifecycleFieldRegistry::class)->registerField(
            LifecyclePoint::JobStart,
            'test-explodes',
            [
                'critical' => $critical,
                'persist' => function (): void {
                    throw new \RuntimeException('handler exploded');
                },
            ],
        );
    }

    public function test_a_critical_handler_takes_the_job_down_with_it(): void
    {
        $this->registerHandler(critical: true);

        $driver = $this->driver();
        app(JobLifecycleService::class)->startShift($driver, []);

        try {
            app(JobLifecycleService::class)->startJob($driver, $this->object(), 'raumen');
            $this->fail('Expected the critical handler to abort the job');
        } catch (\RuntimeException $e) {
            $this->assertSame('handler exploded', $e->getMessage());
        }

        $this->assertSame(0, Job::count());
    }

    public function test_a_normal_handler_keeps_todays_behaviour(): void
    {
        $this->registerHandler(critical: false);

        $driver = $this->driver();
        app(JobLifecycleService::class)->startShift($driver, []);

        $job = app(JobLifecycleService::class)->startJob($driver, $this->object(), 'raumen');

        $this->assertNotNull($job);
        $this->assertSame(1, Job::count());
    }

    public function test_critical_defaults_to_false(): void
    {
        app(LifecycleFieldRegistry::class)->registerField(
            LifecyclePoint::JobStart,
            'test-plain',
            ['persist' => fn () => null],
        );

        $entry = app(LifecycleFieldRegistry::class)->resolve('test-plain');

        $this->assertFalse($entry['critical']);
    }
}
