<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\Extension\PortalNavigationRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalNavRenderTest extends TestCase
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

    private function portalCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Portal Kunde',
            'email' => 'kunde@test.local',
            'password' => Hash::make('password'),
            'portal_enabled' => true,
            'locale' => 'de',
        ]);
    }

    public function test_core_items_render_on_portal_home(): void
    {
        $customer = $this->portalCustomer();

        $response = $this->actingAs($customer, 'customer')->get(route('portal.home'));

        $response->assertOk();
        $response->assertSee(__('portal.nav_jobs'));
        $response->assertSee(__('portal.nav_profile'));
    }

    public function test_module_injected_item_renders(): void
    {
        // A module would call this in its ServiceProvider::boot().
        app(PortalNavigationRegistry::class)->addItem(
            'contracts',
            'portal.nav_jobs', // reuse an existing key as a stand-in label
            'portal.home',
            order: 25,
        );

        $customer = $this->portalCustomer();

        $response = $this->actingAs($customer, 'customer')->get(route('portal.home'));

        $response->assertOk();
        // Two link blocks (desktop + mobile) both iterate the same registry,
        // so the injected route appears at least twice.
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($response->getContent(), 'href="'.route('portal.home').'"'),
        );
    }
}
