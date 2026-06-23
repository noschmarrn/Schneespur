<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\SchneespurModuleClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModuleDescriptionUiTest extends TestCase
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
            'email' => 'desc-ui-admin@test.local',
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

    private function makeCatalogEntry(string $slug, string $descDe): array
    {
        return [
            'slug' => $slug,
            'version' => '1.0.0',
            'name' => ['de' => ucfirst($slug), 'en' => ucfirst($slug)],
            'description' => ['de' => $descDe, 'en' => $descDe],
            'download_url' => "https://example.com/{$slug}.zip",
            'sha256' => hash('sha256', $slug),
            'size_bytes' => 100,
            'info_url' => "https://jenni.noschmarrn.dev/{$slug}/info",
        ];
    }

    public function test_long_description_shows_expand_toggle(): void
    {
        $admin = $this->createAdmin();

        $this->mockCatalog([$this->makeCatalogEntry('long-mod', str_repeat('Lange Beschreibung. ', 30))]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertSee(__('modules.desc_more'));
    }

    public function test_short_description_has_no_expand_toggle(): void
    {
        $admin = $this->createAdmin();

        $this->mockCatalog([$this->makeCatalogEntry('short-mod', 'Kurz.')]);

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.modules.index'));

        $response->assertStatus(200);
        $response->assertDontSee(__('modules.desc_more'));
    }
}
