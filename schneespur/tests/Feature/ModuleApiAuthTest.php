<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Module;
use App\Models\ModuleApiToken;
use App\Models\User;
use App\Services\Extension\ModuleApiRegistrar;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleApiAuthTest extends TestCase
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
            ['slug' => 'example'],
            [
                'version' => '1.0.0',
                'enabled' => true,
                'manifest_json' => [],
                'installed_at' => now(),
            ]
        );

        app(ModuleApiRegistrar::class)->routes('example', 1, function () {
            Route::get('status', fn () => response()->json([
                'status' => 'ok',
                'module' => 'example',
                'version' => '1.0.0',
            ]))->name('status');
        });
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

    private function createToken(string $slug = 'example', ?string $expiresAt = null): array
    {
        $plaintext = bin2hex(random_bytes(32));

        $token = ModuleApiToken::create([
            'module_slug' => $slug,
            'name' => 'Test Token',
            'token_hash' => hash('sha256', $plaintext),
            'expires_at' => $expiresAt,
        ]);

        return [$token, $plaintext];
    }

    public function test_valid_bearer_token_authenticates(): void
    {
        [$token, $plaintext] = $this->createToken();

        $response = $this->getJson('/api/mod/example/v1/status', [
            'Authorization' => "Bearer {$plaintext}",
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'ok', 'module' => 'example']);
    }

    public function test_invalid_bearer_token_returns_401(): void
    {
        $response = $this->getJson('/api/mod/example/v1/status', [
            'Authorization' => 'Bearer invalid-random-token-value',
        ]);

        $response->assertUnauthorized();
        $response->assertJson(['error' => 'Unauthenticated']);
    }

    public function test_expired_token_returns_401(): void
    {
        [$token, $plaintext] = $this->createToken('example', now()->subHour()->toDateTimeString());

        $response = $this->getJson('/api/mod/example/v1/status', [
            'Authorization' => "Bearer {$plaintext}",
        ]);

        $response->assertUnauthorized();
    }

    public function test_missing_auth_returns_401(): void
    {
        $response = $this->getJson('/api/mod/example/v1/status');

        $response->assertUnauthorized();
        $response->assertJson(['error' => 'Unauthenticated']);
    }

    public function test_session_auth_fallback_works(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->getJson('/api/mod/example/v1/status');

        $response->assertOk();
        $response->assertJson(['status' => 'ok', 'module' => 'example']);
    }

    public function test_last_used_at_updated_on_token_auth(): void
    {
        [$token, $plaintext] = $this->createToken();

        $this->assertNull($token->fresh()->last_used_at);

        $this->getJson('/api/mod/example/v1/status', [
            'Authorization' => "Bearer {$plaintext}",
        ]);

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    public function test_token_scoped_to_module_slug(): void
    {
        $otherModule = Module::firstOrCreate(
            ['slug' => 'other-module'],
            [
                'version' => '1.0.0',
                'enabled' => true,
                'manifest_json' => [],
                'installed_at' => now(),
            ]
        );

        [$token, $plaintext] = $this->createToken('other-module');

        $response = $this->getJson('/api/mod/example/v1/status', [
            'Authorization' => "Bearer {$plaintext}",
        ]);

        $response->assertUnauthorized();
    }
}
