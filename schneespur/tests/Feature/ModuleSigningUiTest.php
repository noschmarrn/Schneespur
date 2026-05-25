<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Module;
use App\Models\User;
use App\Services\SchneespurModuleClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModuleSigningUiTest extends TestCase
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
            'email' => 'ui-sig-admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user->fresh();
    }

    private function mockCatalog(array $modules): void
    {
        $mock = $this->mock(SchneespurModuleClient::class);
        $mock->shouldReceive('fetchCatalog')->andReturn(['modules' => $modules]);
    }

    private function makeCatalogEntry(string $slug, array $overrides = []): array
    {
        return array_merge([
            'slug' => $slug,
            'version' => '1.0.0',
            'name' => ['de' => ucfirst($slug), 'en' => ucfirst($slug)],
            'description' => ['de' => 'Beschreibung', 'en' => 'Description'],
            'download_url' => "https://example.com/{$slug}.zip",
            'sha256' => hash('sha256', $slug),
            'size_bytes' => 100,
        ], $overrides);
    }

    public function test_installed_verified_module_shows_signed_badge(): void
    {
        $admin = $this->createAdmin();

        Module::create([
            'slug' => 'verified-mod',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => ['name' => ['de' => 'Verifiziert']],
            'signature_status' => 'verified',
            'installed_at' => now(),
        ]);

        $this->mockCatalog([$this->makeCatalogEntry('verified-mod', [
            'signature' => 'abc123',
            'key_id' => 'key-1',
        ])]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertSee(__('modules.signature_verified'));
    }

    public function test_installed_unsigned_module_shows_unsigned_badge(): void
    {
        $admin = $this->createAdmin();

        Module::create([
            'slug' => 'unsigned-mod',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => ['name' => ['de' => 'Unsigniert']],
            'signature_status' => 'unsigned',
            'installed_at' => now(),
        ]);

        $this->mockCatalog([$this->makeCatalogEntry('unsigned-mod')]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertSee(__('modules.signature_unsigned'));
        $response->assertSee(__('modules.signature_unsigned_tooltip'));
    }

    public function test_installed_failed_module_shows_failed_badge(): void
    {
        $admin = $this->createAdmin();

        Module::create([
            'slug' => 'failed-mod',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => ['name' => ['de' => 'Fehlerhaft']],
            'signature_status' => 'failed',
            'installed_at' => now(),
        ]);

        $this->mockCatalog([$this->makeCatalogEntry('failed-mod')]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertSee(__('modules.signature_failed_badge'));
    }

    public function test_installed_module_with_null_status_shows_no_badge(): void
    {
        $admin = $this->createAdmin();

        Module::create([
            'slug' => 'legacy-mod',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => ['name' => ['de' => 'Legacy']],
            'signature_status' => null,
            'installed_at' => now(),
        ]);

        $this->mockCatalog([$this->makeCatalogEntry('legacy-mod')]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertDontSee(__('modules.signature_verified'));
        $response->assertDontSee(__('modules.signature_unsigned'));
        $response->assertDontSee(__('modules.signature_failed_badge'));
    }

    public function test_available_signed_catalog_module_shows_signed_badge(): void
    {
        $admin = $this->createAdmin();

        $this->mockCatalog([$this->makeCatalogEntry('new-signed', [
            'signature' => 'sig-bytes',
            'key_id' => 'key-2',
        ])]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertSee(__('modules.signature_verified'));
    }

    public function test_available_unsigned_catalog_module_shows_unsigned_badge(): void
    {
        $admin = $this->createAdmin();

        $this->mockCatalog([$this->makeCatalogEntry('new-unsigned')]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertSee(__('modules.signature_unsigned'));
    }
}
