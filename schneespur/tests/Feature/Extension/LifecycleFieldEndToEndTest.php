<?php

namespace Tests\Feature\Extension;

use App\Enums\LifecyclePoint;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Setting;
use App\Models\User;
use App\Services\Extension\LifecycleFieldRegistry;
use App\Services\JobLifecycleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LifecycleFieldEndToEndTest extends TestCase
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

    public function test_job_end_field_is_validated_and_persisted_atomically(): void
    {
        $captured = null;

        app(LifecycleFieldRegistry::class)->registerField(LifecyclePoint::JobEnd, 'demo.salt', [
            'rules' => ['demo_salt' => ['nullable', 'numeric', 'min:0']],
            'persist' => function ($job, array $validated) use (&$captured) {
                $captured = ['job_id' => $job->id, 'salt' => $validated['demo_salt'] ?? null];
            },
        ]);

        $driver = User::create(['name' => 'D', 'email' => 'e2e@test.local', 'password' => Hash::make('password')]);
        $driver->role = UserRole::Driver;
        $driver->save();
        $driver = $driver->fresh();

        $customer = Customer::create(['name' => 'C']);
        $object = CustomerObject::create(['customer_id' => $customer->id, 'name' => 'O']);

        $service = app(JobLifecycleService::class);
        $service->startShift($driver);
        $job = $service->startJob($driver, $object, 'raumen');

        // Set DSGVO fields so EnsureDsgvoInformed middleware lets the request through.
        // Pattern copied from tests/Feature/SlotRegistryTest.php makeDriver/test_driver_dashboard_renders_without_registered_slots.
        $driver->dsgvo_informed_at = now();
        $driver->confirmed_version = (int) Setting::get('dsgvo_template_version', 1);
        $driver->save();
        $driver = $driver->fresh();

        $this->actingAs($driver)
            ->post(route('driver.job.end'), ['notes' => 'ok', 'demo_salt' => 12])
            ->assertSessionHasNoErrors();

        $this->assertSame(['job_id' => $job->id, 'salt' => 12], $captured);
    }
}
