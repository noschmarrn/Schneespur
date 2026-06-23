<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Module;
use App\Models\User;
use App\Services\SchneespurModuleClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModuleTrustLevelUiTest extends TestCase
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
            'email' => 'trust-ui-admin@test.local',
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

    public function test_installed_official_module_shows_blue_badge(): void
    {
        $admin = $this->createAdmin();

        Module::create([
            'slug' => 'official-mod',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => ['name' => ['de' => 'Offiziell']],
            'trust_level' => 'official',
            'installed_at' => now(),
        ]);

        $this->mockCatalog([$this->makeCatalogEntry('official-mod', [
            'trust_level' => 'official',
        ])]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertSee(__('modules.trust_official'));
        $response->assertSee(__('modules.trust_official_tooltip'));
    }

    public function test_installed_verified_module_shows_green_badge(): void
    {
        $admin = $this->createAdmin();

        Module::create([
            'slug' => 'verified-mod',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => ['name' => ['de' => 'Verifiziert']],
            'trust_level' => 'verified',
            'installed_at' => now(),
        ]);

        $this->mockCatalog([$this->makeCatalogEntry('verified-mod', [
            'trust_level' => 'verified',
        ])]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertSee(__('modules.trust_verified'));
        $response->assertSee(__('modules.trust_verified_tooltip'));
    }

    public function test_community_label_is_not_shown(): void
    {
        $admin = $this->createAdmin();

        // The live catalog carries no trust_level, so modules default to no
        // trust badge. Even an explicit community trust_level must not surface
        // the (removed) community label any more.
        Module::create([
            'slug' => 'thirdparty-mod',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => ['name' => ['de' => 'Drittanbieter Mod']],
            'trust_level' => 'community',
            'installed_at' => now(),
        ]);

        $this->mockCatalog([$this->makeCatalogEntry('new-thirdparty', [
            'trust_level' => 'community',
        ])]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertDontSee(__('modules.trust_community'));
    }

    public function test_trust_filter_dropdown_is_rendered(): void
    {
        $admin = $this->createAdmin();

        $this->mockCatalog([]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertSee('id="trustFilter"', false);
        $response->assertSee(__('modules.trust_filter_label'));
        $response->assertSee(__('modules.trust_filter_all'));
    }

    public function test_orphan_module_with_null_trust_level_shows_no_badge(): void
    {
        $admin = $this->createAdmin();

        Module::create([
            'slug' => 'legacy-orphan-mod',
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => ['name' => ['de' => 'Legacy']],
            'trust_level' => null,
            'installed_at' => now(),
        ]);

        $this->mockCatalog([]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertDontSee(__('modules.trust_unknown'));
    }
}
