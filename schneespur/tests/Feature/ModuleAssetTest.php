<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Extension\ModuleAssetRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ModuleAssetTest extends TestCase
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

    private function createAdminUser(): User
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

    public function test_enabled_module_css_rendered_in_admin_layout(): void
    {
        $registry = $this->app->make(ModuleAssetRegistry::class);
        $modulePath = base_path('modules/example');
        $registry->registerAssets('example', $modulePath);

        $user = $this->createAdminUser();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('modules/example/example-abc123.css', false);
        $response->assertSee('<link rel="stylesheet"', false);
    }

    public function test_disabled_module_css_not_rendered_in_admin_layout(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('modules/example', false);
    }

    public function test_module_with_missing_dist_file_logs_warning_without_crash(): void
    {
        $tmpDir = sys_get_temp_dir() . '/module-asset-missing-' . uniqid();
        mkdir($tmpDir . '/dist', 0755, true);
        file_put_contents($tmpDir . '/dist/manifest.json', json_encode([
            ['type' => 'css', 'file' => 'nonexistent.css'],
        ]));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'asset file not found'));
        Log::shouldReceive('debug')->zeroOrMoreTimes();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $registry = $this->app->make(ModuleAssetRegistry::class);
        $registry->registerAssets('broken-mod', $tmpDir);

        $this->assertEmpty($registry->getCss());

        // Cleanup
        @unlink($tmpDir . '/dist/manifest.json');
        @rmdir($tmpDir . '/dist');
        @rmdir($tmpDir);
    }
}
