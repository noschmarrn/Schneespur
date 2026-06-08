<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Customer;
use App\Services\Extension\LocaleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerLocaleValidationTest extends TestCase
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

    private function admin(): User
    {
        $u = User::create(['name' => 'A', 'email' => 'a@test.local', 'password' => Hash::make('password')]);
        $u->role = \App\Enums\UserRole::Admin;
        $u->save();

        return $u->fresh();
    }

    public function test_registered_module_locale_is_accepted_for_customer(): void
    {
        app(LocaleRegistry::class)->add('cs', 'Čeština');

        $response = $this->actingAs($this->admin())->post(route('admin.customers.store'), [
            'name' => 'Tscheche GmbH',
            'locale' => 'cs',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertNotNull(Customer::where('name', 'Tscheche GmbH')->where('locale', 'cs')->first());
    }

    public function test_unregistered_locale_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.customers.store'), [
            'name' => 'Bad Locale',
            'locale' => 'xx',
        ]);

        $response->assertSessionHasErrors('locale');
    }
}
