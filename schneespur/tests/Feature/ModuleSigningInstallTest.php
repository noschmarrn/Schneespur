<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Module;
use App\Models\User;
use App\Services\ModuleSignatureVerifier;
use App\Services\SchneespurModuleClient;
use App\Services\SchneespurModuleInstaller;
use App\Services\SignatureResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModuleSigningInstallTest extends TestCase
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
            'name' => 'Admin User',
            'email' => 'sigtest-admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user->fresh();
    }

    private function makeCatalogEntry(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'test-module',
            'version' => '1.0.0',
            'sha256' => hash('sha256', 'test-zip'),
            'size_bytes' => 8,
            'size' => 8,
            'download_url' => 'https://test-server.example/download/test-module.zip',
            'name' => ['de' => 'Testmodul'],
            'description' => ['de' => 'Ein Test'],
        ], $overrides);
    }

    private function mockVerifier(SignatureResult $result): void
    {
        $mock = $this->mock(ModuleSignatureVerifier::class);
        $mock->shouldReceive('refreshTrust');
        $mock->shouldReceive('verifyModuleManifest')->andReturn($result);
    }

    private function mockClientAndInstaller(array $catalogEntry, bool $installSuccess = true): void
    {
        $mockClient = $this->mock(SchneespurModuleClient::class);
        $mockClient->shouldReceive('fetchCatalog')->andReturn(['modules' => [$catalogEntry]]);
        $mockClient->shouldReceive('downloadModule')->andReturn(tempnam(sys_get_temp_dir(), 'mod'));
        $mockClient->shouldReceive('loadState')->andReturn(['installed' => []]);
        $mockClient->shouldReceive('writeState');

        $mockInstaller = $this->mock(SchneespurModuleInstaller::class);
        $mockInstaller->shouldReceive('install')->andReturn($installSuccess);
        $mockInstaller->shouldReceive('update')->andReturn($installSuccess);
    }

    // ── AdminModuleController install ─────────────────────

    public function test_install_verified_module_stores_verified_status(): void
    {
        $admin = $this->createAdmin();
        $entry = $this->makeCatalogEntry();

        $this->mockVerifier(SignatureResult::verified('key-1'));
        $this->mockClientAndInstaller($entry);

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.install', 'test-module'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');

        $module = Module::where('slug', 'test-module')->first();
        $this->assertNotNull($module);
        $this->assertEquals('verified', $module->signature_status);
    }

    public function test_install_unsigned_module_stores_unsigned_and_flashes_warning(): void
    {
        $admin = $this->createAdmin();
        $entry = $this->makeCatalogEntry();

        $this->mockVerifier(SignatureResult::unsigned());
        $this->mockClientAndInstaller($entry);

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.install', 'test-module'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('warning');

        $module = Module::where('slug', 'test-module')->first();
        $this->assertNotNull($module);
        $this->assertEquals('unsigned', $module->signature_status);
    }

    public function test_install_tampered_module_is_rejected(): void
    {
        $admin = $this->createAdmin();
        $entry = $this->makeCatalogEntry();

        $this->mockVerifier(SignatureResult::failed('key-1', 'Signatur ungültig'));
        $mockClient = $this->mock(SchneespurModuleClient::class);
        $mockClient->shouldReceive('fetchCatalog')->andReturn(['modules' => [$entry]]);

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.install', 'test-module'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('error');

        $this->assertNull(Module::where('slug', 'test-module')->first());
    }

    // ── AdminModuleController update ──────────────────────

    public function test_update_verified_module_stores_verified_status(): void
    {
        $admin = $this->createAdmin();
        $entry = $this->makeCatalogEntry(['version' => '2.0.0']);

        Module::create([
            'slug' => 'test-module',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => [],
            'signature_status' => null,
            'installed_at' => now(),
        ]);

        $this->mockVerifier(SignatureResult::verified('key-1'));
        $this->mockClientAndInstaller($entry);

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.update', 'test-module'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');

        $module = Module::where('slug', 'test-module')->first();
        $this->assertEquals('verified', $module->signature_status);
        $this->assertEquals('2.0.0', $module->version);
    }

    // ── ModulesSync CLI ───────────────────────────────────

    public function test_sync_verified_module_installs_with_status(): void
    {
        $entry = $this->makeCatalogEntry();

        $this->mockVerifier(SignatureResult::verified('key-1'));
        $this->mockClientAndInstaller($entry);

        $this->artisan('schneespur:modules-sync')
            ->assertExitCode(0);

        $module = Module::where('slug', 'test-module')->first();
        $this->assertNotNull($module);
        $this->assertEquals('verified', $module->signature_status);
    }

    public function test_sync_tampered_module_is_skipped(): void
    {
        $entry = $this->makeCatalogEntry();

        $this->mockVerifier(SignatureResult::failed('key-1', 'Signatur ungültig'));

        $mockClient = $this->mock(SchneespurModuleClient::class);
        $mockClient->shouldReceive('fetchCatalog')->andReturn(['modules' => [$entry]]);
        $mockClient->shouldReceive('loadState')->andReturn(['installed' => []]);
        $mockClient->shouldReceive('writeState');

        $this->artisan('schneespur:modules-sync')
            ->expectsOutputToContain('Signaturprüfung fehlgeschlagen')
            ->assertExitCode(0);

        $this->assertNull(Module::where('slug', 'test-module')->first());
    }

    public function test_sync_unsigned_module_installs_with_warning(): void
    {
        $entry = $this->makeCatalogEntry();

        $this->mockVerifier(SignatureResult::unsigned());
        $this->mockClientAndInstaller($entry);

        $this->artisan('schneespur:modules-sync')
            ->expectsOutputToContain('nicht signiert')
            ->assertExitCode(0);

        $module = Module::where('slug', 'test-module')->first();
        $this->assertNotNull($module);
        $this->assertEquals('unsigned', $module->signature_status);
    }

    public function test_sync_trust_refresh_failure_aborts(): void
    {
        $mockVerifier = $this->mock(ModuleSignatureVerifier::class);
        $mockVerifier->shouldReceive('refreshTrust')
            ->andThrow(new \RuntimeException('HTTP 500'));

        $mockClient = $this->mock(SchneespurModuleClient::class);
        $mockClient->shouldReceive('fetchCatalog')->andReturn(['modules' => [['slug' => 'x']]]);

        $this->artisan('schneespur:modules-sync')
            ->expectsOutputToContain('Trust-Refresh fehlgeschlagen')
            ->assertExitCode(1);
    }

    // ── Migration ─────────────────────────────────────────

    public function test_signature_status_column_persists_values(): void
    {
        $module = Module::create([
            'slug' => 'migration-test',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => [],
            'signature_status' => 'verified',
            'installed_at' => now(),
        ]);

        $this->assertEquals('verified', $module->fresh()->signature_status);

        $module->update(['signature_status' => 'unsigned']);
        $this->assertEquals('unsigned', $module->fresh()->signature_status);

        $module->update(['signature_status' => null]);
        $this->assertNull($module->fresh()->signature_status);

        $module->delete();
    }
}
