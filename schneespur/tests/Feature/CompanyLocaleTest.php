<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Services\Extension\LocaleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanyLocaleTest extends TestCase
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
        $u->role = UserRole::Admin;
        $u->save();

        return $u->fresh();
    }

    public function test_admin_can_set_registered_locale_as_default(): void
    {
        app(LocaleRegistry::class)->add('cs', 'Čeština');

        $response = $this->actingAs($this->admin())->post(route('admin.settings.company.update'), [
            'company_name' => 'ACME',
            'season_from' => '11-01',
            'season_to' => '03-31',
            'alert_overdue_hours' => 4,
            'default_locale' => 'cs',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('cs', Setting::get('default_locale'));
    }

    public function test_unregistered_default_locale_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.settings.company.update'), [
            'company_name' => 'ACME',
            'season_from' => '11-01',
            'season_to' => '03-31',
            'alert_overdue_hours' => 4,
            'default_locale' => 'xx',
        ]);

        $response->assertSessionHasErrors('default_locale');
    }
}
