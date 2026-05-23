<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModuleDependencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $baseModulePath;
    private string $childModulePath;
    private string $conflictModulePath;
    private string $cycleModulePath;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }

        $this->baseModulePath = base_path('modules/base-mod');
        $this->childModulePath = base_path('modules/child-mod');
        $this->conflictModulePath = base_path('modules/conflict-mod');
        $this->cycleModulePath = base_path('modules/cycle-mod');
    }

    protected function tearDown(): void
    {
        foreach ([$this->baseModulePath, $this->childModulePath, $this->conflictModulePath, $this->cycleModulePath] as $path) {
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            }
        }

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

    private function createModuleOnDisk(string $slug, array $manifest): void
    {
        $path = base_path("modules/{$slug}");
        File::ensureDirectoryExists($path);
        File::put("{$path}/module.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function createModuleRecord(string $slug, bool $enabled = false, ?string $version = '1.0.0'): Module
    {
        return Module::create([
            'slug' => $slug,
            'version' => $version,
            'enabled' => $enabled,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);
    }

    public function test_enable_succeeds_when_dependency_is_satisfied(): void
    {
        $this->createModuleOnDisk('base-mod', [
            'name' => 'Base',
            'version' => '1.0.0',
            'requires' => [],
            'conflicts' => [],
        ]);
        $this->createModuleOnDisk('child-mod', [
            'name' => 'Child',
            'version' => '1.0.0',
            'requires' => ['base-mod' => '>=1.0.0'],
            'conflicts' => [],
        ]);

        $this->createModuleRecord('base-mod', enabled: true);
        $this->createModuleRecord('child-mod', enabled: false);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.enable', 'child-mod'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');
        $this->assertTrue(Module::where('slug', 'child-mod')->first()->enabled);
    }

    public function test_enable_fails_when_required_module_is_missing(): void
    {
        $this->createModuleOnDisk('child-mod', [
            'name' => 'Child',
            'version' => '1.0.0',
            'requires' => ['base-mod' => '>=1.0.0'],
            'conflicts' => [],
        ]);

        $this->createModuleRecord('child-mod', enabled: false);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.enable', 'child-mod'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('error');
        $this->assertFalse(Module::where('slug', 'child-mod')->first()->enabled);
    }

    public function test_enable_fails_when_conflict_module_is_active(): void
    {
        $this->createModuleOnDisk('base-mod', [
            'name' => 'Base',
            'version' => '1.0.0',
            'requires' => [],
            'conflicts' => [],
        ]);
        $this->createModuleOnDisk('conflict-mod', [
            'name' => 'Conflict',
            'version' => '1.0.0',
            'requires' => [],
            'conflicts' => ['base-mod'],
        ]);

        $this->createModuleRecord('base-mod', enabled: true);
        $this->createModuleRecord('conflict-mod', enabled: false);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.enable', 'conflict-mod'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('error');
        $this->assertFalse(Module::where('slug', 'conflict-mod')->first()->enabled);
    }

    public function test_disable_warns_when_other_modules_depend_on_it(): void
    {
        $this->createModuleOnDisk('base-mod', [
            'name' => 'Base',
            'version' => '1.0.0',
            'requires' => [],
            'conflicts' => [],
        ]);
        $this->createModuleOnDisk('child-mod', [
            'name' => 'Child',
            'version' => '1.0.0',
            'requires' => ['base-mod' => '>=1.0.0'],
            'conflicts' => [],
        ]);

        $this->createModuleRecord('base-mod', enabled: true);
        $this->createModuleRecord('child-mod', enabled: true);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.disable', 'base-mod'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('error');
        $this->assertTrue(Module::where('slug', 'base-mod')->first()->enabled);
    }

    public function test_enable_succeeds_after_dependency_is_activated(): void
    {
        $this->createModuleOnDisk('base-mod', [
            'name' => 'Base',
            'version' => '2.0.0',
            'requires' => [],
            'conflicts' => [],
        ]);
        $this->createModuleOnDisk('child-mod', [
            'name' => 'Child',
            'version' => '1.0.0',
            'requires' => ['base-mod' => '>=1.0.0'],
            'conflicts' => [],
        ]);

        $this->createModuleRecord('base-mod', enabled: false, version: '2.0.0');
        $this->createModuleRecord('child-mod', enabled: false);

        $admin = $this->createAdmin();

        // First: enable won't work because base-mod is inactive
        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.enable', 'child-mod'));
        $response->assertSessionHas('error');

        // Enable base-mod first
        $this->actingAs($admin)
            ->post(route('admin.settings.modules.enable', 'base-mod'));

        // Now child-mod can be enabled
        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.enable', 'child-mod'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');
        $this->assertTrue(Module::where('slug', 'child-mod')->first()->enabled);
    }

    public function test_enable_fails_when_circular_dependency_detected(): void
    {
        $this->createModuleOnDisk('base-mod', [
            'name' => 'Base',
            'version' => '1.0.0',
            'requires' => ['cycle-mod' => '>=1.0.0'],
            'conflicts' => [],
        ]);
        $this->createModuleOnDisk('cycle-mod', [
            'name' => 'Cycle',
            'version' => '1.0.0',
            'requires' => ['base-mod' => '>=1.0.0'],
            'conflicts' => [],
        ]);

        $this->createModuleRecord('base-mod', enabled: false);
        $this->createModuleRecord('cycle-mod', enabled: true);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.enable', 'base-mod'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('error');
        $errorMsg = strtolower(session('error'));
        $this->assertTrue(
            str_contains($errorMsg, 'circular') || str_contains($errorMsg, 'zirkul'),
            "Expected circular dependency error, got: {$errorMsg}"
        );
        $this->assertFalse(Module::where('slug', 'base-mod')->first()->enabled);
    }
}
