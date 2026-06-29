<?php

namespace Tests\Feature\Events;

use App\Enums\JobType;
use App\Enums\UserRole;
use App\Events\Customer\CustomerDeleted;
use App\Events\Customer\CustomerUpdated;
use App\Events\Module\ModuleDisabled;
use App\Events\Module\ModuleEnabled;
use App\Events\Shift\WorkShiftEnded;
use App\Events\Shift\WorkShiftStarted;
use App\Events\User\UserCreated;
use App\Events\User\UserLoggedIn;
use App\Events\User\UserLoggedOut;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Module;
use App\Models\User;
use App\Services\JobLifecycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LifecycleEventsTest extends TestCase
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

    private function createAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user->fresh();
    }

    private function createDriver(string $email = 'driver@test.local'): User
    {
        $user = User::create([
            'name' => 'Driver User',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Driver;
        $user->save();

        return $user->fresh();
    }

    private function createCustomerWithObject(): array
    {
        $customer = Customer::create(['name' => 'Test Customer']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Test Object',
        ]);

        return [$customer, $object];
    }

    // --- WorkShiftStarted ---

    public function test_work_shift_started_fires_on_start_shift(): void
    {
        Event::fake([WorkShiftStarted::class]);

        $driver = $this->createDriver();
        $service = app(JobLifecycleService::class);

        $shift = $service->startShift($driver);

        Event::assertDispatched(WorkShiftStarted::class, function (WorkShiftStarted $e) use ($shift, $driver) {
            return $e->workShift->id === $shift->id && $e->user->id === $driver->id;
        });
    }

    public function test_work_shift_started_fires_on_create_manual_job(): void
    {
        Event::fake([WorkShiftStarted::class, WorkShiftEnded::class]);

        $driver = $this->createDriver();
        [$customer, $object] = $this->createCustomerWithObject();
        $service = app(JobLifecycleService::class);

        $service->createManualJob(
            $driver,
            $object,
            JobType::Raumen->value,
            Carbon::now()->subHour(),
            Carbon::now(),
        );

        Event::assertDispatched(WorkShiftStarted::class, function (WorkShiftStarted $e) use ($driver) {
            return $e->user->id === $driver->id;
        });
    }

    // --- WorkShiftEnded ---

    public function test_work_shift_ended_fires_on_end_shift(): void
    {
        Event::fake([WorkShiftEnded::class]);

        $driver = $this->createDriver();
        $service = app(JobLifecycleService::class);

        $service->startShift($driver);
        $shift = $service->endShift($driver);

        Event::assertDispatched(WorkShiftEnded::class, function (WorkShiftEnded $e) use ($shift, $driver) {
            return $e->workShift->id === $shift->id && $e->user->id === $driver->id;
        });
    }

    public function test_work_shift_ended_fires_on_create_manual_job(): void
    {
        Event::fake([WorkShiftStarted::class, WorkShiftEnded::class]);

        $driver = $this->createDriver();
        [$customer, $object] = $this->createCustomerWithObject();
        $service = app(JobLifecycleService::class);

        $service->createManualJob(
            $driver,
            $object,
            JobType::Raumen->value,
            Carbon::now()->subHour(),
            Carbon::now(),
        );

        Event::assertDispatched(WorkShiftEnded::class, function (WorkShiftEnded $e) use ($driver) {
            return $e->user->id === $driver->id;
        });
    }

    // --- CustomerUpdated ---

    public function test_customer_updated_fires_on_update(): void
    {
        Event::fake([CustomerUpdated::class]);

        $admin = $this->createAdmin();
        $customer = Customer::create(['name' => 'Original Name']);

        $this->actingAs($admin)
            ->put(route('admin.customers.update', $customer), [
                'name' => 'Updated Name',
            ]);

        Event::assertDispatched(CustomerUpdated::class, function (CustomerUpdated $e) use ($customer) {
            return $e->customer->id === $customer->id;
        });
    }

    // --- CustomerDeleted ---

    public function test_customer_deleted_fires_before_delete(): void
    {
        Event::fake([CustomerDeleted::class]);

        $admin = $this->createAdmin();
        $customer = Customer::create(['name' => 'To Delete']);

        $this->actingAs($admin)
            ->delete(route('admin.customers.destroy', $customer));

        Event::assertDispatched(CustomerDeleted::class, function (CustomerDeleted $e) use ($customer) {
            return $e->customer->id === $customer->id && $e->customer->name === 'To Delete';
        });
    }

    // --- UserLoggedIn ---

    public function test_user_logged_in_fires_on_login(): void
    {
        Event::fake([UserLoggedIn::class]);

        User::create([
            'name' => 'Login User',
            'email' => 'login@test.local',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', [
            'email' => 'login@test.local',
            'password' => 'password123',
        ]);

        Event::assertDispatched(UserLoggedIn::class, function (UserLoggedIn $e) {
            return $e->user->email === 'login@test.local';
        });
    }

    // --- UserLoggedOut ---

    public function test_user_logged_out_fires_with_correct_user_after_logout(): void
    {
        Event::fake([UserLoggedOut::class]);

        $user = User::create([
            'name' => 'Logout User',
            'email' => 'logout@test.local',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user)
            ->post('/logout');

        Event::assertDispatched(UserLoggedOut::class, function (UserLoggedOut $e) use ($user) {
            return $e->user->id === $user->id;
        });
    }

    // --- UserCreated (registration path — route disabled by D-05, tested via temporary route) ---

    public function test_user_created_fires_on_registration(): void
    {
        Event::fake([UserCreated::class]);

        \Illuminate\Support\Facades\Route::post('/_test/register', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'store'])
            ->middleware('web');

        $this->post('/_test/register', [
            'name' => 'New User',
            'email' => 'newuser@test.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        Event::assertDispatched(UserCreated::class, function (UserCreated $e) {
            return $e->user->email === 'newuser@test.local';
        });
    }

    // --- UserCreated (driver creation by admin) ---

    public function test_user_created_fires_on_driver_store(): void
    {
        Event::fake([UserCreated::class]);

        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('admin.drivers.store'), [
                'name' => 'New Driver',
                'email' => 'newdriver@test.local',
                'password' => 'password123',
            ]);

        Event::assertDispatched(UserCreated::class, function (UserCreated $e) {
            return $e->user->email === 'newdriver@test.local';
        });
    }

    // --- ModuleEnabled ---

    public function test_module_enabled_fires_on_enable(): void
    {
        Event::fake([ModuleEnabled::class]);

        $admin = $this->createAdmin();
        $module = Module::create([
            'slug' => 'test-module',
            'version' => '1.0.0',
            'enabled' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.modules.enable', 'test-module'));

        Event::assertDispatched(ModuleEnabled::class, function (ModuleEnabled $e) use ($module) {
            return $e->module->id === $module->id && $e->module->slug === 'test-module';
        });
    }

    // --- ModuleDisabled ---

    public function test_module_disabled_fires_on_disable(): void
    {
        Event::fake([ModuleDisabled::class]);

        $admin = $this->createAdmin();
        Module::create([
            'slug' => 'test-module',
            'version' => '1.0.0',
            'enabled' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.modules.disable', 'test-module'));

        Event::assertDispatched(ModuleDisabled::class, function (ModuleDisabled $e) {
            return $e->module->slug === 'test-module';
        });
    }

    // --- ModuleDisabled on remove ---

    public function test_module_disabled_fires_on_remove(): void
    {
        Event::fake([ModuleDisabled::class]);

        $admin = $this->createAdmin();
        Module::create([
            'slug' => 'removable-module',
            'version' => '1.0.0',
            'enabled' => true,
        ]);

        $this->mock(\App\Services\SchneespurModuleInstaller::class, function ($mock) {
            $mock->shouldReceive('remove')->once()->with('removable-module');
        });

        $this->actingAs($admin)
            ->delete(route('admin.settings.modules.remove', 'removable-module'));

        Event::assertDispatched(ModuleDisabled::class, function (ModuleDisabled $e) {
            return $e->module->slug === 'removable-module';
        });
    }
}
