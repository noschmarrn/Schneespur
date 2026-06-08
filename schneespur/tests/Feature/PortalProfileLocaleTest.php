<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Services\Extension\LocaleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalProfileLocaleTest extends TestCase
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

    private function portalCustomer(string $locale = 'de'): Customer
    {
        return Customer::create([
            'name' => 'Kunde',
            'email' => 'k@test.local',
            'password' => Hash::make('password'),
            'portal_enabled' => true,
            'locale' => $locale,
        ]);
    }

    public function test_registered_locale_appears_as_option(): void
    {
        app(LocaleRegistry::class)->add('cs', 'Čeština');
        $customer = $this->portalCustomer();

        $response = $this->actingAs($customer, 'customer')->get(route('portal.profile.edit'));

        $response->assertOk();
        $response->assertSee('value="cs"', false);
        $response->assertSee('Čeština', false);
    }

    public function test_customer_can_switch_to_registered_locale(): void
    {
        app(LocaleRegistry::class)->add('cs', 'Čeština');
        $customer = $this->portalCustomer();

        $response = $this->actingAs($customer, 'customer')->patch(route('portal.profile.update'), [
            'email' => $customer->email,
            'locale' => 'cs',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('cs', $customer->fresh()->locale);
    }
}
