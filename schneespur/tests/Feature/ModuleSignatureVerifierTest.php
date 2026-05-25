<?php

namespace Tests\Feature;

use App\Services\ModuleSignatureVerifier;
use App\Services\SchneespurModuleClient;
use App\Services\SchneespurUpdater;
use App\Services\SignatureResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ModuleSignatureVerifierTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $rootPubkey;
    private string $rootSeckey;
    private string $signerPubkey;
    private string $signerSeckey;
    private string $stateFilePath;

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
        ]);

        $this->stateFilePath = storage_path('app/schneespur_modules_state_test.json');
        config(['schneespur_modules.state_file_path' => $this->stateFilePath]);

        @unlink($this->stateFilePath);
    }

    protected function tearDown(): void
    {
        @unlink($this->stateFilePath);
        parent::tearDown();
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
        $entry = array_merge([
            'slug' => 'test-module',
            'version' => '1.0.0',
            'sha256' => hash('sha256', 'test-zip-content'),
            'size_bytes' => 16,
        ], $overrides);

        return $entry;
    }

    private function signModuleEntry(array $entry): array
    {
        $canonical = SchneespurUpdater::canonicalJson($entry);
        $sig = sodium_crypto_sign_detached($canonical, $this->signerSeckey);

        $entry['signature'] = [
            'key_id' => 'test-key-1',
            'sig' => base64_encode($sig),
            'manifest' => $entry,
        ];

        return $entry;
    }

    private function writeStateWithTrust(array $trustOverrides = []): void
    {
        $trust = $this->makeTrust($trustOverrides);
        $state = [
            'catalog_etag' => null,
            'synced_at' => null,
            'installed' => [],
            'orphans' => [],
            'trust_version' => $trust['trust_version'],
            'valid_keys' => $trust['valid_keys'],
            'revoked_keys' => $trust['revoked_keys'],
            'trust_expires_at' => $trust['expires_at'],
        ];
        file_put_contents($this->stateFilePath, json_encode($state, JSON_PRETTY_PRINT));
    }

    private function makeVerifier(): ModuleSignatureVerifier
    {
        return app(ModuleSignatureVerifier::class);
    }

    // ── refreshTrust tests ─────────────────────────────

    public function test_refresh_trust_stores_valid_trust(): void
    {
        $trust = $this->makeTrust();
        $sig = $this->signTrust($trust);

        Http::fake([
            'test-server.example/api/signing/trust' => Http::response([
                'trust' => $trust,
                'signature' => $sig,
            ]),
        ]);

        $verifier = $this->makeVerifier();
        $verifier->refreshTrust();

        $client = app(SchneespurModuleClient::class);
        $state = $client->loadState();

        $this->assertEquals(1, $state['trust_version']);
        $this->assertCount(1, $state['valid_keys']);
        $this->assertEquals('test-key-1', $state['valid_keys'][0]['key_id']);
        $this->assertNotEmpty($state['trust_expires_at']);
    }

    public function test_refresh_trust_rejects_invalid_signature(): void
    {
        $trust = $this->makeTrust();
        $wrongKeypair = sodium_crypto_sign_keypair();
        $wrongSeckey = sodium_crypto_sign_secretkey($wrongKeypair);
        $canonical = SchneespurUpdater::canonicalJson($trust);
        $badSig = base64_encode(sodium_crypto_sign_detached($canonical, $wrongSeckey));

        Http::fake([
            'test-server.example/api/signing/trust' => Http::response([
                'trust' => $trust,
                'signature' => $badSig,
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Root-Mismatch');

        $this->makeVerifier()->refreshTrust();
    }

    public function test_refresh_trust_rejects_rollback(): void
    {
        $this->writeStateWithTrust(['trust_version' => 5]);

        $trust = $this->makeTrust(['trust_version' => 3]);
        $sig = $this->signTrust($trust);

        Http::fake([
            'test-server.example/api/signing/trust' => Http::response([
                'trust' => $trust,
                'signature' => $sig,
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rollback');

        $this->makeVerifier()->refreshTrust();
    }

    public function test_refresh_trust_rejects_expired(): void
    {
        $trust = $this->makeTrust(['expires_at' => date('c', strtotime('-1 day'))]);
        $sig = $this->signTrust($trust);

        Http::fake([
            'test-server.example/api/signing/trust' => Http::response([
                'trust' => $trust,
                'signature' => $sig,
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('abgelaufen');

        $this->makeVerifier()->refreshTrust();
    }

    public function test_refresh_trust_handles_404(): void
    {
        Http::fake([
            'test-server.example/api/signing/trust' => Http::response(null, 404),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('trust-tool sign');

        $this->makeVerifier()->refreshTrust();
    }

    // ── verifyModuleManifest tests ─────────────────────

    public function test_verify_signed_module_returns_verified(): void
    {
        $this->writeStateWithTrust();

        $entry = $this->makeModuleEntry();
        $signed = $this->signModuleEntry($entry);

        $result = $this->makeVerifier()->verifyModuleManifest($signed);

        $this->assertEquals('verified', $result->status);
        $this->assertEquals('test-key-1', $result->keyId);
        $this->assertTrue($result->isAllowed);
    }

    public function test_verify_unsigned_module_returns_unsigned(): void
    {
        $this->writeStateWithTrust();

        $entry = $this->makeModuleEntry();

        $result = $this->makeVerifier()->verifyModuleManifest($entry);

        $this->assertEquals('unsigned', $result->status);
        $this->assertNull($result->keyId);
        $this->assertTrue($result->isAllowed);
    }

    public function test_verify_tampered_module_returns_failed(): void
    {
        $this->writeStateWithTrust();

        $entry = $this->makeModuleEntry();
        $signed = $this->signModuleEntry($entry);
        $signed['signature']['manifest']['version'] = '9.9.9';

        $result = $this->makeVerifier()->verifyModuleManifest($signed);

        $this->assertEquals('failed', $result->status);
        $this->assertEquals('test-key-1', $result->keyId);
        $this->assertFalse($result->isAllowed);
    }

    public function test_verify_revoked_key_returns_failed(): void
    {
        $this->writeStateWithTrust([
            'revoked_keys' => [
                ['key_id' => 'test-key-1', 'reason' => 'compromised'],
            ],
        ]);

        $entry = $this->makeModuleEntry();
        $signed = $this->signModuleEntry($entry);

        $result = $this->makeVerifier()->verifyModuleManifest($signed);

        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('widerrufen', $result->message);
        $this->assertFalse($result->isAllowed);
    }

    public function test_verify_unknown_key_returns_failed(): void
    {
        $this->writeStateWithTrust([
            'valid_keys' => [
                [
                    'key_id' => 'different-key',
                    'pubkey_b64' => base64_encode($this->signerPubkey),
                    'label' => 'Different Key',
                ],
            ],
        ]);

        $entry = $this->makeModuleEntry();
        $signed = $this->signModuleEntry($entry);

        $result = $this->makeVerifier()->verifyModuleManifest($signed);

        $this->assertEquals('failed', $result->status);
        $this->assertStringContainsString('Unbekannter', $result->message);
        $this->assertFalse($result->isAllowed);
    }

    public function test_verify_expired_trust_returns_expired_trust(): void
    {
        $this->writeStateWithTrust([
            'expires_at' => date('c', strtotime('-1 day')),
        ]);

        $entry = $this->makeModuleEntry();
        $signed = $this->signModuleEntry($entry);

        $result = $this->makeVerifier()->verifyModuleManifest($signed);

        $this->assertEquals('expired_trust', $result->status);
        $this->assertFalse($result->isAllowed);
    }

    // ── verifyZipIntegrity tests ───────────────────────

    public function test_verify_zip_integrity_passes_for_matching_file(): void
    {
        $content = 'test-zip-content';
        $tmp = tempnam(sys_get_temp_dir(), 'sigtest-');
        file_put_contents($tmp, $content);

        $entry = [
            'sha256' => hash('sha256', $content),
            'size_bytes' => strlen($content),
        ];

        try {
            $this->makeVerifier()->verifyZipIntegrity($tmp, $entry);
            $this->assertTrue(true);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_verify_zip_integrity_fails_on_size_mismatch(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sigtest-');
        file_put_contents($tmp, 'short');

        $entry = [
            'sha256' => hash('sha256', 'short'),
            'size_bytes' => 99999,
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Größe stimmt nicht');

        try {
            $this->makeVerifier()->verifyZipIntegrity($tmp, $entry);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_verify_zip_integrity_fails_on_sha256_mismatch(): void
    {
        $content = 'original-content';
        $tmp = tempnam(sys_get_temp_dir(), 'sigtest-');
        file_put_contents($tmp, $content);

        $entry = [
            'sha256' => hash('sha256', 'different-content'),
            'size_bytes' => strlen($content),
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SHA256 stimmt nicht');

        try {
            $this->makeVerifier()->verifyZipIntegrity($tmp, $entry);
        } finally {
            @unlink($tmp);
        }
    }

    // ── SignatureResult value object tests ──────────────

    public function test_signature_result_verified_factory(): void
    {
        $r = SignatureResult::verified('k1');
        $this->assertEquals('verified', $r->status);
        $this->assertEquals('k1', $r->keyId);
        $this->assertTrue($r->isAllowed);
    }

    public function test_signature_result_unsigned_factory(): void
    {
        $r = SignatureResult::unsigned();
        $this->assertEquals('unsigned', $r->status);
        $this->assertNull($r->keyId);
        $this->assertTrue($r->isAllowed);
    }

    public function test_signature_result_failed_factory(): void
    {
        $r = SignatureResult::failed('k1', 'bad');
        $this->assertEquals('failed', $r->status);
        $this->assertEquals('k1', $r->keyId);
        $this->assertFalse($r->isAllowed);
        $this->assertEquals('bad', $r->message);
    }

    public function test_signature_result_expired_trust_factory(): void
    {
        $r = SignatureResult::expiredTrust();
        $this->assertEquals('expired_trust', $r->status);
        $this->assertNull($r->keyId);
        $this->assertFalse($r->isAllowed);
    }
}
