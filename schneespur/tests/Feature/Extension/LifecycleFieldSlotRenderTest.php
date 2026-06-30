<?php

namespace Tests\Feature\Extension;

use App\Enums\LifecyclePoint;
use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Services\Extension\LifecycleFieldRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class LifecycleFieldSlotRenderTest extends TestCase
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
        View::addNamespace('lf-test', __DIR__ . '/_fixtures');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    public function test_shift_start_slot_renders_on_dashboard(): void
    {
        app(LifecycleFieldRegistry::class)->registerField(LifecyclePoint::ShiftStart, 'demo.field', [
            'view' => 'lf-test::lifecycle-field',
        ]);

        $driver = User::create(['name' => 'D', 'email' => 'slot@test.local', 'password' => Hash::make('password')]);
        $driver->role = UserRole::Driver;
        $driver->save();

        // Set DSGVO fields so EnsureDsgvoInformed middleware lets the request through (302→200).
        // Pattern from LifecycleFieldEndToEndTest and SlotRegistryTest::makeDriver.
        $driver->dsgvo_informed_at = now();
        $driver->confirmed_version = (int) Setting::get('dsgvo_template_version', 1);
        $driver->save();

        $this->actingAs($driver->fresh())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-testid="demo-field"', false);
    }
}
