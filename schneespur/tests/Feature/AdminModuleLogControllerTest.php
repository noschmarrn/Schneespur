<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ModLog;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminModuleLogControllerTest extends TestCase
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
            'name' => 'Admin',
            'email' => 'logtest-admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user->fresh();
    }

    private function createModule(string $slug = 'test-module'): Module
    {
        return Module::create([
            'slug' => $slug,
            'version' => '1.0.0',
            'enabled' => true,
        ]);
    }

    public function test_admin_can_view_module_logs(): void
    {
        $admin = $this->createAdmin();
        $module = $this->createModule();

        ModLog::create([
            'module_slug' => $module->slug,
            'level' => 'info',
            'message' => 'Module booted',
            'context' => null,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings.modules.logs', $module->slug));

        $response->assertOk();
        $response->assertSee('Module booted');
    }

    public function test_level_filter_works(): void
    {
        $admin = $this->createAdmin();
        $module = $this->createModule();

        ModLog::create([
            'module_slug' => $module->slug,
            'level' => 'info',
            'message' => 'Info entry',
            'context' => null,
            'created_at' => now(),
        ]);

        ModLog::create([
            'module_slug' => $module->slug,
            'level' => 'error',
            'message' => 'Error entry',
            'context' => null,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings.modules.logs', ['slug' => $module->slug, 'level' => 'error']));

        $response->assertOk();
        $response->assertSee('Error entry');
        $response->assertDontSee('Info entry');
    }

    public function test_missing_module_returns_404(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.settings.modules.logs', 'nonexistent'));

        $response->assertNotFound();
    }

    public function test_non_admin_cannot_access_logs(): void
    {
        $driver = User::create([
            'name' => 'Driver',
            'email' => 'logtest-driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $module = $this->createModule();

        $response = $this->actingAs($driver)->get(route('admin.settings.modules.logs', $module->slug));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_empty_logs_shows_no_logs_message(): void
    {
        $admin = $this->createAdmin();
        $module = $this->createModule();

        $response = $this->actingAs($admin)->get(route('admin.settings.modules.logs', $module->slug));

        $response->assertOk();
        $response->assertSee(__('modules.no_logs'));
    }

    public function test_context_json_displayed(): void
    {
        $admin = $this->createAdmin();
        $module = $this->createModule();

        ModLog::create([
            'module_slug' => $module->slug,
            'level' => 'warning',
            'message' => 'Config issue',
            'context' => ['key' => 'value'],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings.modules.logs', $module->slug));

        $response->assertOk();
        $response->assertSee('Config issue');
    }
}
