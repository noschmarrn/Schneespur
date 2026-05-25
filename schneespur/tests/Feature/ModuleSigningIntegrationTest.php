<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Module;
use App\Models\User;
use App\Services\SchneespurUpdater;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class ModuleSigningIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $rootPubkey;
    private string $rootSeckey;
    private string $signerPubkey;
    private string $signerSeckey;
    private string $stateFilePath;
    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $rootKeypair = sodium_crypto_sign_keypair();
        $this->rootPubkey = sodium_crypto_sign_publickey($rootKeypair);
        $this->rootSeckey = sodium_crypto_sign_secretkey($rootKeypair);

        $signerKeypair = sodium_crypto_sign_keypair();
        $this->signerPubkey = sodium_crypto_sign_publickey($signerKeypair);
        $this->signerSeckey = sodium_crypto_sign_secretkey($signerKeypair);

        config([
            'schneespur_modules.root_pubkey_b64' => base64_encode($this->rootPubkey),
            'schneespur_modules.server_url' => 'https://test-server.example',
            'schneespur_modules.collection_slug' => 'test-collection',
        ]);

        $this->stateFilePath = storage_path('app/schneespur_modules_state_integration_test.json');
        config(['schneespur_modules.state_file_path' => $this->stateFilePath]);

        $this->modulesPath = storage_path('app/test-modules-integration');
        config(['schneespur_modules.modules_path' => $this->modulesPath]);

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }

        @unlink($this->stateFilePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->stateFilePath);
        if (File::isDirectory($this->modulesPath)) {
            File::deleteDirectory($this->modulesPath);
        }
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    private function createAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin-integration@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Admin;
        $user->save();

        return $user->fresh();
    }

    private function makeTrust(array $overrides = []): array
    {
        return array_merge([
            'trust_version' => 1,
            'expires_at' => date('c', strtotime('+30 days')),
            'valid_keys' => [
                [
                    'key_id' => 'test-key-1',
                    'pubkey_b64' => base64_encode($this->signerPubkey),
                    'label' => 'Test Signer',
                ],
            ],
            'revoked_keys' => [],
        ], $overrides);
    }

    private function signTrust(array $trust): string
    {
        $canonical = SchneespurUpdater::canonicalJson($trust);

        return base64_encode(sodium_crypto_sign_detached($canonical, $this->rootSeckey));
    }

    private function makeModuleEntry(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'integration-test-mod',
            'name' => ['de' => 'Testmodul', 'en' => 'Test Module'],
            'description' => ['de' => 'Integrationstest', 'en' => 'Integration test'],
            'version' => '1.0.0',
            'category' => 'test',
            'image' => null,
            'download_url' => 'https://test-server.example/dl/integration-test-mod-1.0.0.zip',
            'sha256' => 'placeholder',
            'size_bytes' => 0,
            'requires_permissions' => [],
            'primary_locale' => 'de',
        ], $overrides);
    }

    private function signModuleEntry(array $entry): array
    {
        $manifest = $entry;
        unset($manifest['signature']);
        $canonical = SchneespurUpdater::canonicalJson($manifest);
        $sig = sodium_crypto_sign_detached($canonical, $this->signerSeckey);

        $entry['signature'] = [
            'key_id' => 'test-key-1',
            'sig' => base64_encode($sig),
            'manifest' => $manifest,
        ];

        return $entry;
    }

    private function createTestZip(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'modzip-');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('module.json', json_encode([
            'slug' => 'integration-test-mod',
            'name' => 'Integration Test Module',
            'version' => '1.0.0',
        ]));
        $zip->close();

        return $tmp;
    }

    private function fakeHttpForInstall(array $trust, string $trustSig, array $catalogModules, string $zipPath): void
    {
        $zipContent = file_get_contents($zipPath);

        Http::fake([
            'test-server.example/api/modules/test-collection' => Http::response([
                'collection' => ['slug' => 'test-collection'],
                'modules' => $catalogModules,
            ]),
            'test-server.example/api/signing/trust' => Http::response([
                'trust' => $trust,
                'signature' => $trustSig,
            ]),
            'test-server.example/dl/*' => Http::response($zipContent),
        ]);
    }

    // ── Test 1: Signed module installs successfully ────────

    public function test_signed_module_installs_successfully(): void
    {
        $zipPath = $this->createTestZip();
        $zipSha = hash_file('sha256', $zipPath);
        $zipSize = filesize($zipPath);

        $entry = $this->makeModuleEntry([
            'sha256' => $zipSha,
            'size_bytes' => $zipSize,
        ]);
        $signed = $this->signModuleEntry($entry);

        $trust = $this->makeTrust();
        $trustSig = $this->signTrust($trust);

        $this->fakeHttpForInstall($trust, $trustSig, [$signed], $zipPath);
        @unlink($zipPath);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.install', 'integration-test-mod'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');

        $module = Module::where('slug', 'integration-test-mod')->first();
        $this->assertNotNull($module);
        $this->assertEquals('verified', $module->signature_status);
        $this->assertEquals('1.0.0', $module->version);
        $this->assertTrue($module->enabled);

        $this->assertTrue(File::isDirectory($this->modulesPath . '/integration-test-mod'));
    }

    // ── Test 2: Tampered ZIP is rejected ───────────────────

    public function test_tampered_zip_is_rejected(): void
    {
        $zipPath = $this->createTestZip();
        $zipSha = hash_file('sha256', $zipPath);
        $zipSize = filesize($zipPath);

        $entry = $this->makeModuleEntry([
            'sha256' => $zipSha,
            'size_bytes' => $zipSize,
        ]);
        $signed = $this->signModuleEntry($entry);

        $trust = $this->makeTrust();
        $trustSig = $this->signTrust($trust);

        // Tamper with the ZIP: append a byte so SHA256 changes but size also changes
        $tamperedContent = file_get_contents($zipPath) . 'X';
        @unlink($zipPath);

        Http::fake([
            'test-server.example/api/modules/test-collection' => Http::response([
                'collection' => ['slug' => 'test-collection'],
                'modules' => [$signed],
            ]),
            'test-server.example/api/signing/trust' => Http::response([
                'trust' => $trust,
                'signature' => $trustSig,
            ]),
            'test-server.example/dl/*' => Http::response($tamperedContent),
        ]);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.install', 'integration-test-mod'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('error');

        $this->assertNull(Module::where('slug', 'integration-test-mod')->first());
    }

    // ── Test 3: Revoked key blocks install ─────────────────

    public function test_revoked_key_blocks_install(): void
    {
        $zipPath = $this->createTestZip();
        $zipSha = hash_file('sha256', $zipPath);
        $zipSize = filesize($zipPath);

        $entry = $this->makeModuleEntry([
            'sha256' => $zipSha,
            'size_bytes' => $zipSize,
        ]);
        $signed = $this->signModuleEntry($entry);

        $trust = $this->makeTrust([
            'revoked_keys' => [
                ['key_id' => 'test-key-1', 'reason' => 'compromised'],
            ],
        ]);
        $trustSig = $this->signTrust($trust);

        $this->fakeHttpForInstall($trust, $trustSig, [$signed], $zipPath);
        @unlink($zipPath);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.install', 'integration-test-mod'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('error');

        $this->assertNull(Module::where('slug', 'integration-test-mod')->first());
    }

    // ── Test 4: Unsigned module installs with warning ──────

    public function test_unsigned_module_installs_with_warning(): void
    {
        $zipPath = $this->createTestZip();
        $zipSha = hash_file('sha256', $zipPath);
        $zipSize = filesize($zipPath);

        $entry = $this->makeModuleEntry([
            'sha256' => $zipSha,
            'size_bytes' => $zipSize,
        ]);
        // Do NOT sign — leave entry without signature field

        $trust = $this->makeTrust();
        $trustSig = $this->signTrust($trust);

        $this->fakeHttpForInstall($trust, $trustSig, [$entry], $zipPath);
        @unlink($zipPath);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.install', 'integration-test-mod'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('warning');

        $module = Module::where('slug', 'integration-test-mod')->first();
        $this->assertNotNull($module);
        $this->assertEquals('unsigned', $module->signature_status);
        $this->assertTrue($module->enabled);
    }

    // ── Test 5: Expired trust blocks install ───────────────

    public function test_expired_trust_blocks_install(): void
    {
        $zipPath = $this->createTestZip();
        $zipSha = hash_file('sha256', $zipPath);
        $zipSize = filesize($zipPath);

        $entry = $this->makeModuleEntry([
            'sha256' => $zipSha,
            'size_bytes' => $zipSize,
        ]);
        $signed = $this->signModuleEntry($entry);

        $trust = $this->makeTrust([
            'expires_at' => date('c', strtotime('-1 day')),
        ]);
        $trustSig = $this->signTrust($trust);

        $this->fakeHttpForInstall($trust, $trustSig, [$signed], $zipPath);
        @unlink($zipPath);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.install', 'integration-test-mod'));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('error');

        $this->assertNull(Module::where('slug', 'integration-test-mod')->first());
    }
}
