<?php

namespace Tests\Feature\Extension;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Setting;
use App\Models\User;
use App\Services\Extension\JobTypeRegistry;
use App\Services\JobLifecycleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JobTypeValidationTest extends TestCase
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

    private function makeDriver(string $email): User
    {
        $driver = User::create(['name' => 'D', 'email' => $email, 'password' => Hash::make('password')]);
        $driver->role = UserRole::Driver;
        $driver->dsgvo_informed_at = now();
        $driver->confirmed_version = (int) Setting::get('dsgvo_template_version', 1);
        $driver->save();

        return $driver->fresh();
    }

    public function test_module_type_passes_validation_after_registration(): void
    {
        app(JobTypeRegistry::class)->registerType('maehen', 'job.type_maehen');

        $driver = $this->makeDriver('drv@test.local');

        $customer = Customer::create(['name' => 'C']);
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'O']);
        app(JobLifecycleService::class)->startShift($driver);

        $this->actingAs($driver)
            ->post(route('driver.job.start'), [
                'customer_object_id' => $object->id,
                'type' => 'maehen',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_unregistered_type_fails_validation(): void
    {
        $driver = $this->makeDriver('drv2@test.local');

        $customer = Customer::create(['name' => 'C']);
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'O']);
        app(JobLifecycleService::class)->startShift($driver);

        $this->actingAs($driver)
            ->post(route('driver.job.start'), [
                'customer_object_id' => $object->id,
                'type' => 'not_a_type',
            ])
            ->assertSessionHasErrors('type');
    }
}
