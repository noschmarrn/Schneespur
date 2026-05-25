<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ModuleSignatureVerifier
{
    private string $rootPubkeyRaw;
    private string $serverUrl;
    private SchneespurModuleClient $moduleClient;

    public function __construct(SchneespurModuleClient $moduleClient)
    {
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('ext-sodium is required for module signature verification');
        }

        $b64 = config('schneespur_modules.root_pubkey_b64');
        $this->rootPubkeyRaw = base64_decode($b64, true);
        if (strlen($this->rootPubkeyRaw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('Configured module root_pubkey_b64 has wrong length');
        }

        $this->serverUrl = rtrim(config('schneespur_modules.server_url'), '/');
        $this->moduleClient = $moduleClient;
    }

    public function refreshTrust(): void
    {
        $state = $this->moduleClient->loadState();

        $r = Http::acceptJson()->timeout(15)
            ->get("{$this->serverUrl}/api/signing/trust");

        if ($r->status() === 404) {
            throw new RuntimeException(
                'Server liefert noch keine signed trust.json — '
                . 'Operator muss zuerst trust-tool sign --initial laufen lassen.'
            );
        }
        if ($r->failed()) {
            throw new RuntimeException("HTTP {$r->status()} bei trust-fetch");
        }

        $body   = $r->json();
        $trust  = $body['trust'] ?? null;
        $sigB64 = $body['signature'] ?? null;
        if (! is_array($trust) || ! is_string($sigB64)) {
            throw new RuntimeException('Trust-Response hat unerwartete Form');
        }

        $sigRaw = base64_decode($sigB64, true);
        if ($sigRaw === false || strlen($sigRaw) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new RuntimeException('Trust-Signature-Base64 ungültig');
        }

        $canonical = SchneespurUpdater::canonicalJson($trust);
        if (! sodium_crypto_sign_verify_detached($sigRaw, $canonical, $this->rootPubkeyRaw)) {
            Log::error('schneespur-modules: trust signature verification failed — root key mismatch or MITM');
            throw new RuntimeException(
                'Trust-Signatur ungültig — Root-Mismatch oder MITM'
            );
        }

        $newVersion   = (int) ($trust['trust_version'] ?? 0);
        $localVersion = (int) ($state['trust_version'] ?? 0);
        if ($newVersion < $localVersion) {
            Log::error('schneespur-modules: trust rollback attempt', [
                'server_version' => $newVersion,
                'local_version' => $localVersion,
            ]);
            throw new RuntimeException(
                "Trust-Rollback-Versuch: server={$newVersion} < local={$localVersion}"
            );
        }

        $expires = strtotime((string) ($trust['expires_at'] ?? ''));
        if ($expires === false || $expires <= time()) {
            Log::error('schneespur-modules: trust list expired', [
                'expires_at' => $trust['expires_at'] ?? null,
            ]);
            throw new RuntimeException(
                'Trust-Liste ist abgelaufen — Operator muss neu signieren'
            );
        }

        $state['trust_version']    = $newVersion;
        $state['valid_keys']       = $trust['valid_keys'];
        $state['revoked_keys']     = $trust['revoked_keys'];
        $state['trust_expires_at'] = (string) ($trust['expires_at'] ?? '');
        $this->moduleClient->writeState($state);

        Log::info('schneespur-modules: trust refreshed', [
            'trust_version' => $newVersion,
            'valid_key_count' => count($trust['valid_keys']),
            'revoked_key_count' => count($trust['revoked_keys']),
        ]);
    }

    public function verifyModuleManifest(array $moduleEntry): SignatureResult
    {
        $signature = $moduleEntry['signature'] ?? null;

        if ($signature === null) {
            Log::warning('schneespur-modules: unsigned module', [
                'slug' => $moduleEntry['slug'] ?? 'unknown',
            ]);
            return SignatureResult::unsigned();
        }

        $state = $this->moduleClient->loadState();

        $expiresAt = $state['trust_expires_at'] ?? '';
        $expires = strtotime($expiresAt);
        if ($expiresAt === '' || $expires === false || $expires <= time()) {
            Log::error('schneespur-modules: trust expired during module verification', [
                'slug' => $moduleEntry['slug'] ?? 'unknown',
                'trust_expires_at' => $expiresAt,
            ]);
            return SignatureResult::expiredTrust();
        }

        $keyId = $signature['key_id'] ?? null;
        $sigB64 = $signature['sig'] ?? null;

        if (! is_string($keyId) || ! is_string($sigB64)) {
            Log::error('schneespur-modules: malformed signature field', [
                'slug' => $moduleEntry['slug'] ?? 'unknown',
            ]);
            return SignatureResult::failed($keyId ?? 'unknown', 'Signatur-Feld hat ungültiges Format');
        }

        foreach ($state['revoked_keys'] ?? [] as $rk) {
            if (($rk['key_id'] ?? null) === $keyId) {
                Log::error('schneespur-modules: module signed with revoked key', [
                    'slug' => $moduleEntry['slug'] ?? 'unknown',
                    'key_id' => $keyId,
                    'reason' => $rk['reason'] ?? 'unknown',
                ]);
                return SignatureResult::failed($keyId, 'Signing-Key wurde widerrufen: ' . ($rk['reason'] ?? 'unknown'));
            }
        }

        $match = null;
        foreach ($state['valid_keys'] ?? [] as $vk) {
            if (($vk['key_id'] ?? null) === $keyId) {
                $match = $vk;
                break;
            }
        }
        if ($match === null) {
            Log::error('schneespur-modules: unknown signing key', [
                'slug' => $moduleEntry['slug'] ?? 'unknown',
                'key_id' => $keyId,
            ]);
            return SignatureResult::failed($keyId, 'Unbekannter Signing-Key — nicht in Trust-Liste');
        }

        $pubRaw = base64_decode($match['pubkey_b64'], true);
        if ($pubRaw === false || strlen($pubRaw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return SignatureResult::failed($keyId, 'Pubkey-Format in Trust-Liste ungültig');
        }

        $sigRaw = base64_decode($sigB64, true);
        if ($sigRaw === false || strlen($sigRaw) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return SignatureResult::failed($keyId, 'Modul-Signatur-Base64 ungültig');
        }

        $manifest = $signature['manifest'] ?? $moduleEntry;
        $canonical = SchneespurUpdater::canonicalJson($manifest);
        if (! sodium_crypto_sign_verify_detached($sigRaw, $canonical, $pubRaw)) {
            Log::error('schneespur-modules: module signature verification failed', [
                'slug' => $moduleEntry['slug'] ?? 'unknown',
                'key_id' => $keyId,
            ]);
            return SignatureResult::failed($keyId, 'Signatur ungültig — manipuliert oder beschädigt');
        }

        Log::info('schneespur-modules: module signature verified', [
            'slug' => $moduleEntry['slug'] ?? 'unknown',
            'key_id' => $keyId,
        ]);
        return SignatureResult::verified($keyId);
    }

    public function verifyZipIntegrity(string $zipPath, array $moduleEntry): void
    {
        $expectedSha256 = $moduleEntry['sha256'] ?? null;
        $expectedSize = $moduleEntry['size_bytes'] ?? null;

        if (! is_string($expectedSha256) || ! is_int($expectedSize)) {
            throw new RuntimeException('Module entry fehlen sha256 oder size_bytes Felder');
        }

        clearstatcache(true, $zipPath);
        $actualSize = filesize($zipPath);
        if ($actualSize !== $expectedSize) {
            throw new RuntimeException(
                "Größe stimmt nicht: {$actualSize} vs signiert {$expectedSize}"
            );
        }

        $actualSha = hash_file('sha256', $zipPath);
        if (! hash_equals($expectedSha256, $actualSha)) {
            throw new RuntimeException(
                "SHA256 stimmt nicht: {$actualSha} vs signiert {$expectedSha256}"
            );
        }
    }
}
