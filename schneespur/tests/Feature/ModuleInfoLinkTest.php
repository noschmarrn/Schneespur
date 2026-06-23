<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Module;
use App\Models\User;
use App\Services\SchneespurModuleClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModuleInfoLinkTest extends TestCase
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
            'email' => 'info-link-admin@test.local',
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
            'info_url' => "https://jenni.noschmarrn.dev/{$slug}/info",
        ], $overrides);
    }

    public function test_available_module_shows_info_link(): void
    {
        $admin = $this->createAdmin();

        $this->mockCatalog([$this->makeCatalogEntry('new-mod')]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertSee('https://jenni.noschmarrn.dev/new-mod/info', false);
        $response->assertSee(__('modules.module_info'));
    }

    public function test_installed_module_shows_info_link(): void
    {
        $admin = $this->createAdmin();

        Module::create([
            'slug' => 'inst-mod',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => ['name' => ['de' => 'Installiert']],
            'installed_at' => now(),
        ]);

        $this->mockCatalog([$this->makeCatalogEntry('inst-mod')]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertSee('https://jenni.noschmarrn.dev/inst-mod/info', false);
    }

    public function test_no_signature_badge_is_rendered(): void
    {
        $admin = $this->createAdmin();

        Module::create([
            'slug' => 'sig-mod',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => ['name' => ['de' => 'Sig']],
            'signature_status' => 'unsigned',
            'installed_at' => now(),
        ]);

        $this->mockCatalog([$this->makeCatalogEntry('sig-mod')]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertDontSee(__('modules.signature_unsigned'));
        $response->assertDontSee(__('modules.signature_verified'));
    }

    public function test_non_http_info_url_scheme_is_dropped(): void
    {
        $admin = $this->createAdmin();

        $this->mockCatalog([$this->makeCatalogEntry('evil-mod', [
            'info_url' => 'javascript:alert(document.cookie)',
        ])]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertDontSee('javascript:alert', false);
        $response->assertDontSee(__('modules.module_info'));
    }

    public function test_orphan_module_without_catalog_has_no_info_link(): void
    {
        $admin = $this->createAdmin();

        Module::create([
            'slug' => 'orphan-mod',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => ['name' => ['de' => 'Orphan']],
            'installed_at' => now(),
        ]);

        $this->mockCatalog([]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertDontSee('/orphan-mod/info', false);
    }
}
