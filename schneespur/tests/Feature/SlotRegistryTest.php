<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use App\Services\Extension\SlotRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class SlotRegistryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SlotRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new SlotRegistry;

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

    public function test_append_registers_and_renders_view(): void
    {
        View::addLocation(__DIR__ . '/../fixtures/views');

        $this->registry->append('test.slot', 'slot-alpha');

        $html = $this->registry->render('test.slot');

        $this->assertStringContainsString('ALPHA_CONTENT', $html);
    }

    public function test_multiple_appends_render_in_order(): void
    {
        View::addLocation(__DIR__ . '/../fixtures/views');

        $this->registry->append('test.slot', 'slot-alpha', [], 200);
        $this->registry->append('test.slot', 'slot-beta', [], 50);

        $html = $this->registry->render('test.slot');

        $posAlpha = strpos($html, 'ALPHA_CONTENT');
        $posBeta = strpos($html, 'BETA_CONTENT');

        $this->assertNotFalse($posAlpha);
        $this->assertNotFalse($posBeta);
        $this->assertLessThan($posAlpha, $posBeta, 'Beta (order 50) should appear before Alpha (order 200)');
    }

    public function test_replace_overrides_appends(): void
    {
        View::addLocation(__DIR__ . '/../fixtures/views');

        $this->registry->append('test.slot', 'slot-alpha', [], 100);
        $this->registry->append('test.slot', 'slot-beta', [], 200);
        $this->registry->replace('test.slot', 'slot-replace');

        $html = $this->registry->render('test.slot');

        $this->assertStringContainsString('REPLACE_CONTENT', $html);
        $this->assertStringNotContainsString('ALPHA_CONTENT', $html);
        $this->assertStringNotContainsString('BETA_CONTENT', $html);
    }

    public function test_replace_conflict_logs_warning(): void
    {
        View::addLocation(__DIR__ . '/../fixtures/views');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message) {
                return str_contains($message, "slot 'test.slot'")
                    && str_contains($message, 'slot-replace')
                    && str_contains($message, 'slot-alpha')
                    && str_contains($message, 'last-wins');
            });

        $this->registry->replace('test.slot', 'slot-replace');
        $this->registry->replace('test.slot', 'slot-alpha');
    }

    public function test_render_returns_empty_for_unregistered_slot(): void
    {
        $html = $this->registry->render('nonexistent.slot');

        $this->assertSame('', $html);
    }

    public function test_permission_filtering_excludes_unauthorized_slots(): void
    {
        View::addLocation(__DIR__ . '/../fixtures/views');

        Gate::define('test.special-perm', fn (User $user) => false);

        $this->registry->append('test.slot', 'slot-alpha', [], 100, 'test.special-perm');

        $user = User::create([
            'name' => 'Test User',
            'email' => 'perm-test@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Driver;
        $user->save();

        $html = $this->registry->render('test.slot', $user->fresh());

        $this->assertStringNotContainsString('ALPHA_CONTENT', $html);
    }

    public function test_blade_directive_renders_slot_in_view(): void
    {
        View::addLocation(__DIR__ . '/../fixtures/views');

        $appRegistry = $this->app->make(SlotRegistry::class);
        $appRegistry->append('test.blade-slot', 'slot-alpha');

        $html = View::make('blade-slot-test')->render();

        $this->assertStringContainsString('ALPHA_CONTENT', $html);
        $this->assertStringContainsString('before', $html);
        $this->assertStringContainsString('after', $html);
    }

    public function test_existing_admin_pages_render_without_slots(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-slot@test.local',
            'password' => Hash::make('password'),
        ]);
        $admin->role = UserRole::Admin;
        $admin->save();

        $response = $this->actingAs($admin->fresh())->get(route('admin.dashboard'));

        $response->assertStatus(200);
    }

    public function test_driver_slot_append_renders_in_driver_layout_context(): void
    {
        View::addLocation(__DIR__ . '/../fixtures/views');

        $this->registry->append('driver.content.after', 'slot-alpha');

        $html = $this->registry->render('driver.content.after');

        $this->assertStringContainsString('ALPHA_CONTENT', $html);
    }

    public function test_driver_slot_replace_overrides_appends(): void
    {
        View::addLocation(__DIR__ . '/../fixtures/views');

        $this->registry->append('driver.topbar.actions', 'slot-alpha', [], 100);
        $this->registry->append('driver.topbar.actions', 'slot-beta', [], 200);
        $this->registry->replace('driver.topbar.actions', 'slot-replace');

        $html = $this->registry->render('driver.topbar.actions');

        $this->assertStringContainsString('REPLACE_CONTENT', $html);
        $this->assertStringNotContainsString('ALPHA_CONTENT', $html);
        $this->assertStringNotContainsString('BETA_CONTENT', $html);
    }

    public function test_driver_slot_names_follow_convention(): void
    {
        $expectedSlots = [
            'driver.head.after',
            'driver.topbar.actions',
            'driver.content.before',
            'driver.content.after',
            'driver.bottom-nav.after',
        ];

        View::addLocation(__DIR__ . '/../fixtures/views');

        foreach ($expectedSlots as $slot) {
            $this->registry->append($slot, 'slot-alpha');
        }

        $registeredSlots = $this->registry->getSlotNames();

        foreach ($expectedSlots as $slot) {
            $this->assertContains($slot, $registeredSlots, "Slot '{$slot}' should be registered");
        }
    }

    public function test_driver_dashboard_renders_without_registered_slots(): void
    {
        $driver = User::create([
            'name' => 'Driver User',
            'email' => 'driver-slot@test.local',
            'password' => Hash::make('password'),
        ]);
        $driver->role = UserRole::Driver;
        $driver->dsgvo_informed_at = now();
        $driver->confirmed_version = (int) \App\Models\Setting::get('dsgvo_template_version', 1);
        $driver->save();

        $response = $this->actingAs($driver->fresh())->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_driver_layout_contains_extension_slot_directives(): void
    {
        $layoutContent = file_get_contents(resource_path('views/layouts/driver.blade.php'));

        $expectedDirectives = [
            "@extensionSlot('driver.head.after')",
            "@extensionSlot('driver.topbar.actions')",
            "@extensionSlot('driver.content.before')",
            "@extensionSlot('driver.content.after')",
            "@extensionSlot('driver.bottom-nav.after')",
        ];

        foreach ($expectedDirectives as $directive) {
            $this->assertStringContainsString($directive, $layoutContent, "Driver layout should contain {$directive}");
        }
    }

    public function test_portal_slot_append_renders_in_portal_layout_context(): void
    {
        View::addLocation(__DIR__ . '/../fixtures/views');

        $this->registry->append('portal.content.after', 'slot-alpha');

        $html = $this->registry->render('portal.content.after');

        $this->assertStringContainsString('ALPHA_CONTENT', $html);
    }

    public function test_portal_slot_replace_overrides_appends(): void
    {
        View::addLocation(__DIR__ . '/../fixtures/views');

        $this->registry->append('portal.nav.after', 'slot-alpha', [], 100);
        $this->registry->append('portal.nav.after', 'slot-beta', [], 200);
        $this->registry->replace('portal.nav.after', 'slot-replace');

        $html = $this->registry->render('portal.nav.after');

        $this->assertStringContainsString('REPLACE_CONTENT', $html);
        $this->assertStringNotContainsString('ALPHA_CONTENT', $html);
        $this->assertStringNotContainsString('BETA_CONTENT', $html);
    }

    public function test_portal_slot_names_follow_convention(): void
    {
        $expectedSlots = [
            'portal.head.after',
            'portal.nav.after',
            'portal.content.before',
            'portal.content.after',
            'portal.footer.before',
        ];

        View::addLocation(__DIR__ . '/../fixtures/views');

        foreach ($expectedSlots as $slot) {
            $this->registry->append($slot, 'slot-alpha');
        }

        $registeredSlots = $this->registry->getSlotNames();

        foreach ($expectedSlots as $slot) {
            $this->assertContains($slot, $registeredSlots, "Slot '{$slot}' should be registered");
        }
    }

    public function test_portal_layout_contains_extension_slot_directives(): void
    {
        $layoutContent = file_get_contents(resource_path('views/layouts/portal.blade.php'));

        $expectedDirectives = [
            "@extensionSlot('portal.head.after')",
            "@extensionSlot('portal.nav.after')",
            "@extensionSlot('portal.content.before')",
            "@extensionSlot('portal.content.after')",
            "@extensionSlot('portal.footer.before')",
        ];

        foreach ($expectedDirectives as $directive) {
            $this->assertStringContainsString($directive, $layoutContent, "Portal layout should contain {$directive}");
        }
    }

    public function test_portal_home_renders_without_registered_slots(): void
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'portal-slot@test.local',
            'password' => 'password',
            'portal_enabled' => true,
        ]);

        $response = $this->actingAs($customer, 'customer')->get(route('portal.home'));

        $response->assertStatus(200);
    }
}
