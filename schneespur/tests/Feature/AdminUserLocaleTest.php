<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Extension\LocaleRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserLocaleTest extends TestCase
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

    public function test_admin_can_assign_locale_on_update(): void
    {
        app(LocaleRegistry::class)->add('cs', 'Čeština');
        $admin = $this->admin();
        $driver = User::create(['name' => 'Pavel', 'email' => 'p@test.local', 'password' => Hash::make('password')]);
        $driver->role = UserRole::Driver;
        $driver->save();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $driver), [
            'name' => 'Pavel',
            'email' => 'p@test.local',
            'locale' => 'cs',
            'roles' => [],
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSame('cs', $driver->fresh()->locale);
    }

    public function test_unregistered_locale_rejected_on_update(): void
    {
        $admin = $this->admin();
        $driver = User::create(['name' => 'P', 'email' => 'p2@test.local', 'password' => Hash::make('password')]);
        $driver->role = UserRole::Driver;
        $driver->save();

        $response = $this->actingAs($admin)->put(route('admin.users.update', $driver), [
            'name' => 'P',
            'email' => 'p2@test.local',
            'locale' => 'xx',
            'roles' => [],
        ]);

        $response->assertSessionHasErrors('locale');
    }
}
