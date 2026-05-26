<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ModLog;
use App\Models\Module;
use App\Models\User;
use App\Services\ModuleLogger;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModuleLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }

        $this->module = Module::firstOrCreate(
            ['slug' => 'test-logger'],
            [
                'version' => '1.0.0',
                'enabled' => true,
                'manifest_json' => [],
                'installed_at' => now(),
            ]
        );
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    private function createAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user->fresh();
    }

    private function createDriver(): User
    {
        $user = User::create([
            'name' => 'Driver User',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Driver;
        $user->save();

        return $user->fresh();
    }

    public function test_mod_log_can_be_created_via_module_logger(): void
    {
        $logger = app(ModuleLogger::class);

        $log = $logger->info('test-logger', 'Module booted successfully', ['version' => '1.0.0']);

        $this->assertDatabaseHas('mod_logs', [
            'id' => $log->id,
            'module_slug' => 'test-logger',
            'level' => 'info',
            'message' => 'Module booted successfully',
        ]);
        $this->assertEquals(['version' => '1.0.0'], $log->context);
    }

    public function test_mod_log_update_throws_logic_exception(): void
    {
        $log = ModLog::create([
            'module_slug' => 'test-logger',
            'level' => 'info',
            'message' => 'Test entry',
            'created_at' => now(),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('mod_logs is insert-only');

        $log->update(['message' => 'Changed']);
    }

    public function test_mod_log_delete_throws_logic_exception(): void
    {
        $log = ModLog::create([
            'module_slug' => 'test-logger',
            'level' => 'info',
            'message' => 'Test entry',
            'created_at' => now(),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('mod_logs is insert-only');

        $log->delete();
    }

    public function test_admin_can_view_module_logs_page(): void
    {
        $admin = $this->createAdmin();

        ModLog::create([
            'module_slug' => 'test-logger',
            'level' => 'info',
            'message' => 'Visible log entry',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.logs', ['slug' => 'test-logger']));

        $response->assertOk();
        $response->assertSee('Visible log entry');
    }

    public function test_module_logs_page_filters_by_level(): void
    {
        $admin = $this->createAdmin();

        ModLog::create([
            'module_slug' => 'test-logger',
            'level' => 'info',
            'message' => 'Info level message here',
            'created_at' => now(),
        ]);
        ModLog::create([
            'module_slug' => 'test-logger',
            'level' => 'error',
            'message' => 'Error level message here',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.logs', ['slug' => 'test-logger', 'level' => 'error']));

        $response->assertOk();
        $response->assertSee('Error level message here');
        $response->assertDontSee('Info level message here');
    }

    public function test_module_logs_page_returns_404_for_unknown_module(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.logs', ['slug' => 'nonexistent-module']));

        $response->assertNotFound();
    }

    public function test_driver_cannot_access_module_logs_page(): void
    {
        $driver = $this->createDriver();

        $response = $this->actingAs($driver)
            ->get(route('admin.settings.modules.logs', ['slug' => 'test-logger']));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_guest_cannot_access_module_logs_page(): void
    {
        $response = $this->get(route('admin.settings.modules.logs', ['slug' => 'test-logger']));

        $response->assertRedirect(route('login'));
    }

    public function test_module_logs_paginated(): void
    {
        $admin = $this->createAdmin();

        for ($i = 1; $i <= 30; $i++) {
            ModLog::create([
                'module_slug' => 'test-logger',
                'level' => 'info',
                'message' => "Paginated entry [{$i}]",
                'created_at' => now()->subSeconds(30 - $i),
            ]);
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.logs', ['slug' => 'test-logger']));

        $response->assertOk();
        $response->assertSee('Paginated entry [30]');
        $response->assertDontSee('Paginated entry [5]');
        $response->assertSee('page=2');
    }
}
