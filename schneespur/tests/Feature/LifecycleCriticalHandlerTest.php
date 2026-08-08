<?php

namespace Tests\Feature;

use App\Enums\LifecyclePoint;
use App\Exceptions\JobLifecycleException;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\Setting;
use App\Models\User;
use App\Services\Extension\LifecycleFieldRegistry;
use App\Services\JobLifecycleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    protected function setUp(): void
    {
        parent::setUp();
        $lock = storage_path('app/installed.lock');
        if (! file_exists($lock)) {
            @mkdir(dirname($lock), 0755, true);
            file_put_contents($lock, 'test');
        }
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

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

    /**
     * A driver ready to pass the `dsgvo` web-middleware — needed only for the
     * HTTP-level test, which goes through routes/web.php's
     * ['auth', 'dsgvo', 'driver'] group. Pattern copied from
     * tests/Feature/Extension/JobTypeValidationTest.php::makeDriver().
     */
    private function driverPastDsgvo(): User
    {
        $user = $this->driver();
        $user->dsgvo_informed_at = now();
        $user->confirmed_version = (int) Setting::get('dsgvo_template_version', 1);
        $user->save();

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
                    // A dedicated exception type, not \RuntimeException: PHPUnit's
                    // own AssertionFailedError (thrown by $this->fail() below) also
                    // descends from \RuntimeException, so a broad `catch
                    // (\RuntimeException $e)` around the assertion would silently
                    // swallow a failed assertion instead of failing the test.
                    throw new HandlerExplodedException('handler exploded');
                },
            ],
        );
    }

    public function test_a_critical_handler_takes_the_job_down_with_it(): void
    {
        Log::spy();

        $this->registerHandler(critical: true);

        $driver = $this->driver();
        app(JobLifecycleService::class)->startShift($driver, []);

        try {
            app(JobLifecycleService::class)->startJob($driver, $this->object(), 'raumen');
            $this->fail('Expected the critical handler to abort the job');
        } catch (JobLifecycleException $e) {
            // The driver-facing message is the generic, translated one — the
            // handler's raw message survives only as the cause.
            $this->assertInstanceOf(HandlerExplodedException::class, $e->getPrevious());
            $this->assertSame('handler exploded', $e->getPrevious()->getMessage());
        }

        $this->assertSame(0, Job::count());

        // A critical failure must ALSO be logged, not only thrown — pin this
        // so deleting the Log::warning/DiagnosticManager block in the
        // registry (while keeping the throw) would fail this test too.
        Log::shouldHaveReceived('warning')->atLeast()->once();
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

    /**
     * The controller layer only catches JobLifecycleException
     * (DriverJobController::start:44). Before the JobLifecycleService-level
     * wrap, a critical handler's raw exception passed straight through into
     * Laravel's exception handler: an unhandled 500, no flash message, lost
     * form — for a driver in the field, on exactly the scenario this
     * feature exists for. This proves the redirect-with-flash path instead.
     */
    public function test_a_critical_handler_failure_reaches_the_driver_as_a_flash_not_a_500(): void
    {
        $this->registerHandler(critical: true);

        $driver = $this->driverPastDsgvo();
        app(JobLifecycleService::class)->startShift($driver, []);
        $object = $this->object();

        $response = $this->actingAs($driver)->post(route('driver.job.start'), [
            'customer_object_id' => $object->id,
            'type' => 'raumen',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $response->assertSessionHasNoErrors();
        $this->assertSame(0, Job::count());
    }
}

/**
 * Deliberately extends \Exception, not \RuntimeException — PHPUnit's own
 * AssertionFailedError (thrown by $this->fail()) descends from
 * \RuntimeException, so this type is kept off that branch entirely. See the
 * comment in registerHandler() above.
 */
class HandlerExplodedException extends \Exception
{
}
