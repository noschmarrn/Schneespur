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

class ModuleTrustLevelIntegrationTest extends TestCase
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

        $this->stateFilePath = storage_path('app/schneespur_modules_state_trust_integration_test.json');
        config(['schneespur_modules.state_file_path' => $this->stateFilePath]);

        $this->modulesPath = storage_path('app/test-modules-trust-integration');
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
            'email' => 'admin-trust-integration@test.local',
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

    private function makeModuleEntry(string $slug, array $overrides = []): array
    {
        return array_merge([
            'slug' => $slug,
            'name' => ['de' => 'Testmodul', 'en' => 'Test Module'],
            'description' => ['de' => 'Integrationstest', 'en' => 'Integration test'],
            'version' => '1.0.0',
            'category' => 'test',
            'image' => null,
            'download_url' => "https://test-server.example/dl/{$slug}-1.0.0.zip",
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

    private function createTestZip(string $slug, string $version = '1.0.0'): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'modzip-trust-');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('module.json', json_encode([
            'slug' => $slug,
            'name' => 'Trust Integration Test Module',
            'version' => $version,
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

    // ── Test 1: Official trust_level propagates through install ──

    public function test_official_trust_level_propagates_through_install(): void
    {
        $slug = 'trust-official-mod';
        $zipPath = $this->createTestZip($slug);
        $zipSha = hash_file('sha256', $zipPath);
        $zipSize = filesize($zipPath);

        $entry = $this->makeModuleEntry($slug, [
            'sha256' => $zipSha,
            'size_bytes' => $zipSize,
            'trust_level' => 'official',
        ]);
        $signed = $this->signModuleEntry($entry);

        $trust = $this->makeTrust();
        $trustSig = $this->signTrust($trust);

        $this->fakeHttpForInstall($trust, $trustSig, [$signed], $zipPath);
        @unlink($zipPath);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.install', $slug));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');

        $module = Module::where('slug', $slug)->first();
        $this->assertNotNull($module);
        $this->assertSame('official', $module->trust_level);
        $this->assertSame('verified', $module->signature_status);
    }

    // ── Test 2: Community trust_level propagates through install ──

    public function test_community_trust_level_propagates_through_install(): void
    {
        $slug = 'trust-community-mod';
        $zipPath = $this->createTestZip($slug);
        $zipSha = hash_file('sha256', $zipPath);
        $zipSize = filesize($zipPath);

        $entry = $this->makeModuleEntry($slug, [
            'sha256' => $zipSha,
            'size_bytes' => $zipSize,
            'trust_level' => 'community',
        ]);
        // Unsigned — community module without signature
        $trust = $this->makeTrust();
        $trustSig = $this->signTrust($trust);

        $this->fakeHttpForInstall($trust, $trustSig, [$entry], $zipPath);
        @unlink($zipPath);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.install', $slug));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('warning');

        $module = Module::where('slug', $slug)->first();
        $this->assertNotNull($module);
        $this->assertSame('community', $module->trust_level);
        $this->assertSame('unsigned', $module->signature_status);
    }

    // ── Test 3: Missing trust_level stays null (no forced community) ──

    public function test_missing_trust_level_defaults_to_null(): void
    {
        $slug = 'trust-missing-mod';
        $zipPath = $this->createTestZip($slug);
        $zipSha = hash_file('sha256', $zipPath);
        $zipSize = filesize($zipPath);

        $entry = $this->makeModuleEntry($slug, [
            'sha256' => $zipSha,
            'size_bytes' => $zipSize,
        ]);
        // No trust_level key at all — normalizeModule must NOT force a misleading
        // "community" classification; trust_level stays null.
        unset($entry['trust_level']);

        $signed = $this->signModuleEntry($entry);

        $trust = $this->makeTrust();
        $trustSig = $this->signTrust($trust);

        $this->fakeHttpForInstall($trust, $trustSig, [$signed], $zipPath);
        @unlink($zipPath);

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.modules.install', $slug));

        $response->assertRedirect(route('admin.settings.modules.index'));
        $response->assertSessionHas('success');

        $module = Module::where('slug', $slug)->first();
        $this->assertNotNull($module);
        $this->assertNull($module->trust_level);
    }

    // ── Test 4: Module update changes trust_level ──

    public function test_module_update_changes_trust_level(): void
    {
        $slug = 'trust-update-mod';

        // Prepare both ZIPs upfront
        $zipPath1 = $this->createTestZip($slug, '1.0.0');
        $zipSha1 = hash_file('sha256', $zipPath1);
        $zipSize1 = filesize($zipPath1);
        $zipContent1 = file_get_contents($zipPath1);
        @unlink($zipPath1);

        $zipPath2 = $this->createTestZip($slug, '2.0.0');
        $zipSha2 = hash_file('sha256', $zipPath2);
        $zipSize2 = filesize($zipPath2);
        $zipContent2 = file_get_contents($zipPath2);
        @unlink($zipPath2);

        $trust = $this->makeTrust();
        $trustSig = $this->signTrust($trust);

        // Phase 1 catalog: community, unsigned
        $entry1 = $this->makeModuleEntry($slug, [
            'sha256' => $zipSha1,
            'size_bytes' => $zipSize1,
            'trust_level' => 'community',
        ]);

        // Phase 2 catalog: verified, signed
        $entry2 = $this->makeModuleEntry($slug, [
            'version' => '2.0.0',
            'download_url' => "https://test-server.example/dl/{$slug}-2.0.0.zip",
            'sha256' => $zipSha2,
            'size_bytes' => $zipSize2,
            'trust_level' => 'verified',
        ]);
        $signed2 = $this->signModuleEntry($entry2);

        $trustResponse = Http::response([
            'trust' => $trust,
            'signature' => $trustSig,
        ]);

        Http::fake([
            'test-server.example/api/modules/test-collection' => Http::sequence()
                ->push(['collection' => ['slug' => 'test-collection'], 'modules' => [$entry1]])
                ->push(['collection' => ['slug' => 'test-collection'], 'modules' => [$signed2]]),
            'test-server.example/api/signing/trust' => $trustResponse,
            'test-server.example/dl/*' => Http::sequence()
                ->push($zipContent1)
                ->push($zipContent2),
        ]);

        $admin = $this->createAdmin();

        // Phase 1: Install community module
        $this->actingAs($admin)
            ->post(route('admin.settings.modules.install', $slug));

        $module = Module::where('slug', $slug)->first();
        $this->assertNotNull($module);
        $this->assertSame('community', $module->trust_level);

        // Phase 2: Update to verified
        $updateResponse = $this->actingAs($admin)
            ->post(route('admin.settings.modules.update', $slug));

        $updateResponse->assertRedirect(route('admin.settings.modules.index'));
        $updateResponse->assertSessionHas('success');

        $module->refresh();
        $this->assertSame('verified', $module->trust_level);
        $this->assertSame('2.0.0', $module->version);
    }
}
