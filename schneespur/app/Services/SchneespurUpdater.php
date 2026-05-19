<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZipArchive;

class SchneespurUpdater
{
    private string $rootPubkeyRaw;
    private string $baseUrl;
    private string $slug;
    private string $statePath;
    private string $stagingDir;

    public function __construct()
    {
        $b64 = config('schneespur_update.root_pubkey_b64');

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('ext-sodium is required for update verification');
        }

        $this->rootPubkeyRaw = base64_decode($b64, true);
        if (strlen($this->rootPubkeyRaw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('Configured root_pubkey_b64 has wrong length');
        }

        $this->baseUrl    = rtrim(config('schneespur_update.base_url'), '/');
        $this->slug       = config('schneespur_update.slug');
        $this->statePath  = config('schneespur_update.state_path');
        $this->stagingDir = config('schneespur_update.staging_dir');
    }

    // ── Trust & Manifest (unchanged logic) ──────────────

    public function refreshTrust(): void
    {
        $state = $this->loadState();

        $r = Http::acceptJson()->timeout(15)
            ->get("{$this->baseUrl}/api/signing/trust");

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

        $canonical = self::canonicalJson($trust);
        if (! sodium_crypto_sign_verify_detached($sigRaw, $canonical, $this->rootPubkeyRaw)) {
            throw new RuntimeException(
                'Trust-Signatur ungültig — Root-Mismatch oder MITM'
            );
        }

        $newVersion   = (int) ($trust['trust_version'] ?? 0);
        $localVersion = (int) ($state['trust_version'] ?? 0);
        if ($newVersion < $localVersion) {
            throw new RuntimeException(
                "Trust-Rollback-Versuch: server={$newVersion} < local={$localVersion}"
            );
        }

        $expires = strtotime((string) ($trust['expires_at'] ?? ''));
        if ($expires === false || $expires <= time()) {
            throw new RuntimeException(
                'Trust-Liste ist abgelaufen — Operator muss neu signieren'
            );
        }

        $state['trust_version']    = $newVersion;
        $state['valid_keys']       = $trust['valid_keys'];
        $state['revoked_keys']     = $trust['revoked_keys'];
        $state['trust_expires_at'] = (string) ($trust['expires_at'] ?? '');
        $this->writeState($state);
    }

    public function checkForUpdate(): ?array
    {
        $this->refreshTrust();

        $state = $this->loadState();

        $r = Http::acceptJson()->timeout(15)
            ->get("{$this->baseUrl}/api/projects/{$this->slug}/manifest");

        if ($r->status() === 404) {
            Log::info('schneespur-update: kein signiertes Release verfügbar');
            $this->writeLastCheck($state, false);

            return null;
        }
        if ($r->failed()) {
            throw new RuntimeException("HTTP {$r->status()} beim manifest-fetch");
        }

        $body         = $r->json();
        $manifest     = $body['manifest'] ?? null;
        $signatureB64 = $body['signature'] ?? null;
        if (! is_array($manifest) || ! is_string($signatureB64)) {
            throw new RuntimeException('Manifest-Response hat unerwartete Form');
        }

        $keyId = $manifest['key_id'] ?? null;

        foreach ($state['revoked_keys'] ?? [] as $rk) {
            if (($rk['key_id'] ?? null) === $keyId) {
                throw new RuntimeException(
                    "Signing-Key {$keyId} wurde widerrufen, reason=" . ($rk['reason'] ?? 'unknown')
                );
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
            throw new RuntimeException(
                'Unbekannter Signing-Key — Trust-Layer-Mismatch'
            );
        }

        $pubRaw = base64_decode($match['pubkey_b64'], true);
        if ($pubRaw === false || strlen($pubRaw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('valid_keys-Pubkey-Format ungültig');
        }

        if (($manifest['project'] ?? null) !== $this->slug) {
            throw new RuntimeException("Falsches Manifest: project={$manifest['project']}");
        }

        if (($manifest['manifest_schema_version'] ?? null) !== 2) {
            throw new RuntimeException(
                'Manifest-Schema-Version ungültig — erwartet 2, '
                . 'bekam ' . var_export($manifest['manifest_schema_version'] ?? null, true)
            );
        }

        $sigRaw = base64_decode($signatureB64, true);
        if ($sigRaw === false || strlen($sigRaw) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new RuntimeException('Signature-Base64 ungültig');
        }

        $canonical = self::canonicalJson($manifest);
        if (! sodium_crypto_sign_verify_detached($sigRaw, $canonical, $pubRaw)) {
            throw new RuntimeException('Signatur ungültig — MITM oder beschädigt');
        }

        // Same-version short-circuit first: if the server still serves the
        // currently installed release (same counter, same version), this is
        // not a rollback — just "you're up to date". The counter check below
        // would otherwise misfire after every successful install, because
        // the next manifest fetch carries the exact same counter that was
        // committed during install.
        if ($manifest['version'] === ($state['current_version'] ?? '')) {
            $this->writeLastCheck($state, false);

            return null;
        }

        $newCounter = (int) $manifest['counter'];
        if ($newCounter <= (int) ($state['last_counter'] ?? 0)) {
            throw new RuntimeException(
                "Rollback-Versuch: counter={$newCounter} <= zuletzt={$state['last_counter']}"
            );
        }

        $this->writeLastCheck($state, true, $manifest);

        return $manifest;
    }

    // ── Download & Verify ──────────────────────────

    public function downloadAndVerifyZip(array $manifest): string
    {
        $this->logPhase('download', 'start', ['version' => $manifest['version']]);

        $url = $this->baseUrl . $manifest['url'];
        $tmp = tempnam(sys_get_temp_dir(), 'schneespur-');

        $r = Http::timeout(120)->withOptions(['sink' => $tmp])->get($url);

        if ($r->failed()) {
            $this->safeUnlink($tmp);
            $this->logPhase('download', 'failed', ['http_status' => $r->status()]);
            throw new RuntimeException("ZIP-Download HTTP {$r->status()}");
        }

        $this->logPhase('download', 'complete', ['path' => $tmp]);
        $this->logPhase('verify', 'start');

        clearstatcache(true, $tmp);
        $actualSize = filesize($tmp);
        if ($actualSize !== (int) $manifest['size_bytes']) {
            $this->safeUnlink($tmp);
            $this->logPhase('verify', 'failed', ['reason' => 'size_mismatch']);
            throw new RuntimeException(
                "Grösse stimmt nicht: {$actualSize} vs signiert {$manifest['size_bytes']}"
            );
        }

        $actualSha = hash_file('sha256', $tmp);
        if (! hash_equals($manifest['sha256'], $actualSha)) {
            $this->safeUnlink($tmp);
            $this->logPhase('verify', 'failed', ['reason' => 'sha256_mismatch']);
            throw new RuntimeException(
                "sha256 stimmt nicht: {$actualSha} vs signiert {$manifest['sha256']}"
            );
        }

        $this->logPhase('verify', 'complete');

        return $tmp;
    }

    // ── Preflight ──────────────────────────────────

    public function canInstall(): array
    {
        $checks = [];

        $checks['sodium'] = function_exists('sodium_crypto_sign_verify_detached');
        $checks['zip'] = class_exists(ZipArchive::class);

        $baseDir = base_path();
        $checks['writable'] = is_writable($baseDir);

        $stagingParent = dirname($this->stagingDir);
        try {
            $this->ensureDirectory($stagingParent);
        } catch (RuntimeException) {
            $checks['disk_space'] = false;

            return $checks;
        }
        $freeSpace = disk_free_space($stagingParent);
        $checks['disk_space'] = $freeSpace !== false && $freeSpace > 100 * 1024 * 1024;

        return $checks;
    }

    // ── Extract with ZIP Safety ──────────────────────

    public function extractAndStage(string $zipPath): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ext-zip is required for update installation');
        }

        $this->logPhase('extract', 'start');
        $this->cleanStaging();

        $this->ensureDirectory($this->stagingDir);

        $zip = new ZipArchive;
        $result = $zip->open($zipPath);
        if ($result !== true) {
            throw new RuntimeException("ZIP konnte nicht geöffnet werden: error code {$result}");
        }

        $this->validateZipEntries($zip);

        $prefix = $this->detectCommonPrefix($zip);

        if ($prefix === null) {
            $zip->extractTo($this->stagingDir);
        } else {
            $this->logPhase('extract', 'stripping_prefix', ['prefix' => $prefix]);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if ($entry === false || $entry === '' || $entry === $prefix) {
                    continue;
                }
                if (str_starts_with($entry, '__MACOSX/')) {
                    continue;
                }

                $relative = substr($entry, strlen($prefix));
                if ($relative === '') {
                    continue;
                }

                $dest = $this->stagingDir . '/' . $relative;

                if (str_ends_with($entry, '/')) {
                    $this->ensureDirectory($dest);
                    continue;
                }

                $this->ensureDirectory(dirname($dest));

                $contents = $zip->getFromIndex($i);
                if ($contents === false || file_put_contents($dest, $contents) === false) {
                    $zip->close();
                    throw new RuntimeException("ZIP-Extraktion fehlgeschlagen: {$entry}");
                }
            }
        }

        $zip->close();

        $this->logPhase('extract', 'complete', ['files' => $this->countFiles($this->stagingDir)]);

        return $this->stagingDir;
    }

    /**
     * Detect whether all (non-metadata) ZIP entries share one common
     * top-level folder. Returns the prefix (incl. trailing slash) when so,
     * null when the ZIP is flat or has mixed top-level entries.
     *
     * Mirrors the logic in SchneespurModuleInstaller so update ZIPs that
     * wrap their content in a versioned folder (the build.sh convention)
     * are unwrapped during extraction instead of leaving a stray
     * schneespur-X.Y.Z/ subdirectory in the live install.
     */
    private function detectCommonPrefix(ZipArchive $zip): ?string
    {
        $prefix = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false || $entry === '') {
                continue;
            }
            if (str_starts_with($entry, '__MACOSX/')) {
                continue;
            }

            $slash = strpos($entry, '/');
            if ($slash === false) {
                return null;
            }

            $top = substr($entry, 0, $slash + 1);

            if ($prefix === null) {
                $prefix = $top;
            } elseif ($prefix !== $top) {
                return null;
            }
        }

        return $prefix;
    }

    private function validateZipEntries(ZipArchive $zip): void
    {
        $resolvedStaging = realpath($this->stagingDir) ?: $this->stagingDir;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                throw new RuntimeException("ZIP-Eintrag #{$i} konnte nicht gelesen werden");
            }

            $name = $stat['name'];

            if (str_contains($name, '..')) {
                throw new RuntimeException(
                    "ZIP-Path-Traversal blockiert: '{$name}' enthält '..'"
                );
            }

            if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
                throw new RuntimeException(
                    "ZIP-absoluter-Pfad blockiert: '{$name}'"
                );
            }

            $resolved = realpath($this->stagingDir . '/' . dirname($name));
            if ($resolved !== false && ! str_starts_with($resolved, $resolvedStaging)) {
                throw new RuntimeException(
                    "ZIP-Eintrag verlässt Staging-Verzeichnis: '{$name}'"
                );
            }

            if (function_exists('posix_getpwuid')) {
                $externalAttr = $zip->getExternalAttributesIndex($i, $opsys, $attr);
                if ($externalAttr && $opsys === ZipArchive::OPSYS_UNIX) {
                    $fileType = ($attr >> 16) & 0xF000;
                    if ($fileType === 0xA000) {
                        throw new RuntimeException(
                            "ZIP-Symlink blockiert: '{$name}'"
                        );
                    }
                }
            }
        }
    }

    // ── Install (atomic, with backup + rollback) ────────

    public function install(string $zipPath, array $manifest): void
    {
        $this->logPhase('install', 'start', ['version' => $manifest['version']]);

        $preflight = $this->canInstall();
        if (in_array(false, $preflight, true)) {
            $failed = array_keys(array_filter($preflight, fn ($v) => ! $v));
            throw new RuntimeException('Preflight fehlgeschlagen: ' . implode(', ', $failed));
        }

        $backupDir = $this->createPreUpdateBackup();
        $this->logPhase('backup', 'complete', ['path' => $backupDir]);

        $secret = bin2hex(random_bytes(16));
        Artisan::call('down', ['--secret' => $secret]);
        $this->logPhase('maintenance', 'enabled');

        try {
            $this->extractAndStage($zipPath);

            $this->logPhase('copy', 'start');
            $this->copyFiles();
            $this->logPhase('copy', 'complete');

            $this->logPhase('migrate', 'start');
            Artisan::call('migrate', ['--force' => true]);
            $this->logPhase('migrate', 'complete');

            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            $this->logPhase('cache', 'complete');

            $this->commitState($manifest);
            $this->logPhase('state', 'committed', ['version' => $manifest['version']]);

        } catch (\Throwable $e) {
            $this->logPhase('install', 'failed', [
                'error'      => $e->getMessage(),
                'backup_dir' => $backupDir,
            ]);

            $this->writeRecoveryInfo($manifest, $backupDir, $e->getMessage());

            throw new RuntimeException(
                __('update.install_failed', ['error' => $e->getMessage()])
                . ' — Recovery: php artisan schneespur:update-recover'
            );
        } finally {
            $this->cleanStaging();
        }

        Artisan::call('up');
        $this->logPhase('maintenance', 'disabled');
        $this->logPhase('install', 'complete', ['version' => $manifest['version']]);

        $this->cleanOldBackups(2);
    }

    // ── File Copy (no silent errors) ────────────────────

    private function copyFiles(): void
    {
        $source = $this->stagingDir;
        $target = base_path();

        $skip = ['.env', 'storage', 'bootstrap/cache'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $copied = 0;
        $dirs   = 0;

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);

            foreach ($skip as $prefix) {
                if ($relative === $prefix || str_starts_with($relative, $prefix . '/') || str_starts_with($relative, $prefix . DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }

            $dest = $target . '/' . $relative;

            if ($item->isDir()) {
                if (! is_dir($dest)) {
                    $this->ensureDirectory($dest);
                    $dirs++;
                }
            } else {
                $destDir = dirname($dest);
                $this->ensureDirectory($destDir);
                if (! copy($item->getPathname(), $dest)) {
                    throw new RuntimeException("Datei konnte nicht kopiert werden: {$relative}");
                }
                $copied++;
            }
        }

        $this->logPhase('copy', 'stats', ['files' => $copied, 'dirs' => $dirs]);
    }

    // ── Pre-Update Backup ──────────────────────────────

    private function createPreUpdateBackup(): string
    {
        $backupBase = config('schneespur_update.backup_dir', storage_path('app/schneespur_backups'));
        $state = $this->loadState();
        $version = $state['current_version'] ?: 'unknown';
        $backupDir = $backupBase . '/' . $version . '_' . date('Ymd_His');

        $this->ensureDirectory($backupDir, 0755);

        $criticalFiles = [
            'composer.json',
            'composer.lock',
            'artisan',
            'bootstrap/app.php',
            'bootstrap/providers.php',
            'config/app.php',
        ];

        $basePath = base_path();
        foreach ($criticalFiles as $file) {
            $src = $basePath . '/' . $file;
            if (! is_file($src)) {
                continue;
            }
            $dst = $backupDir . '/' . $file;
            $dstDir = dirname($dst);
            $this->ensureDirectory($dstDir);
            if (! copy($src, $dst)) {
                throw new RuntimeException("Backup-Kopie fehlgeschlagen: {$file}");
            }
        }

        $stateBackup = $backupDir . '/schneespur_update_state.json';
        if (is_file($this->statePath)) {
            if (! copy($this->statePath, $stateBackup)) {
                throw new RuntimeException('Backup der State-Datei fehlgeschlagen');
            }
        }

        return $backupDir;
    }

    private function cleanOldBackups(int $keep): void
    {
        $backupBase = config('schneespur_update.backup_dir', storage_path('app/schneespur_backups'));
        if (! is_dir($backupBase)) {
            return;
        }

        $dirs = [];
        foreach (new \DirectoryIterator($backupBase) as $entry) {
            if ($entry->isDot() || ! $entry->isDir()) {
                continue;
            }
            $dirs[$entry->getPathname()] = $entry->getMTime();
        }

        arsort($dirs);
        $toDelete = array_slice(array_keys($dirs), $keep);

        foreach ($toDelete as $dir) {
            $this->recursiveDelete($dir);
        }
    }

    // ── Recovery ────────────────────────────────────────

    private function writeRecoveryInfo(array $manifest, string $backupDir, string $error): void
    {
        $info = [
            'failed_at'        => now()->toIso8601String(),
            'target_version'   => $manifest['version'],
            'error'            => $error,
            'backup_dir'       => $backupDir,
            'maintenance_mode' => true,
            'recovery_steps'   => [
                '1. php artisan schneespur:update-recover',
                '2. Or manually: php artisan up',
                '3. Check logs: storage/logs/schneespur-update.log',
            ],
        ];

        $path = storage_path('app/schneespur_update_recovery.json');
        $this->atomicJsonWrite($path, $info);
    }

    public function getRecoveryInfo(): ?array
    {
        $path = storage_path('app/schneespur_update_recovery.json');
        if (! is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    public function clearRecoveryInfo(): void
    {
        $path = storage_path('app/schneespur_update_recovery.json');
        if (is_file($path)) {
            $this->safeUnlink($path);
        }
    }

    public function restoreFromBackup(string $backupDir): bool
    {
        if (! is_dir($backupDir)) {
            $this->logPhase('recovery', 'failed', ['reason' => 'backup_dir_missing', 'path' => $backupDir]);

            return false;
        }

        $this->logPhase('recovery', 'start', ['backup' => $backupDir]);
        $basePath = base_path();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($backupDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $restored = 0;
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }
            $relative = substr($item->getPathname(), strlen($backupDir) + 1);

            if ($relative === 'schneespur_update_state.json') {
                if (! copy($item->getPathname(), $this->statePath)) {
                    $this->logPhase('recovery', 'warning', ['file' => 'state', 'action' => 'copy_failed']);
                }
                $restored++;

                continue;
            }

            $dest = $basePath . '/' . $relative;
            $destDir = dirname($dest);
            try {
                $this->ensureDirectory($destDir);
            } catch (RuntimeException) {
                continue;
            }
            if (copy($item->getPathname(), $dest)) {
                $restored++;
            }
        }

        $this->logPhase('recovery', 'complete', ['files_restored' => $restored]);

        return $restored > 0;
    }

    // ── Post-Install Integrity Check ────────────────────

    public function verifyInstallation(array $manifest): array
    {
        $result = [
            'ok'       => true,
            'checks'   => [],
            'warnings' => [],
        ];

        $criticalPaths = [
            'artisan',
            'bootstrap/app.php',
            'public/index.php',
            'vendor/autoload.php',
        ];

        foreach ($criticalPaths as $path) {
            $full = base_path($path);
            if (! is_file($full)) {
                $result['ok'] = false;
                $result['checks'][$path] = 'missing';
            } else {
                $result['checks'][$path] = 'ok';
            }
        }

        $state = $this->loadState();
        if ($state['current_version'] !== $manifest['version']) {
            $result['ok'] = false;
            $result['warnings'][] = 'State-Version stimmt nicht: '
                . $state['current_version'] . ' vs ' . $manifest['version'];
        }

        return $result;
    }

    // ── State Persistence (atomic) ─────────────────────

    public function commitState(array $manifest): void
    {
        $state = $this->loadState();
        $state['last_counter']    = (int) $manifest['counter'];
        $state['current_version'] = $manifest['version'];
        $state['updated_at']      = now()->toIso8601String();
        $this->writeState($state);
    }

    public function getState(): array
    {
        return $this->loadState();
    }

    public function loadState(): array
    {
        if (! is_file($this->statePath)) {
            return [
                'last_counter'     => 0,
                'current_version'  => '',
                'trust_version'    => 0,
                'valid_keys'       => [],
                'revoked_keys'     => [],
                'trust_expires_at' => '',
                'last_check'       => null,
            ];
        }

        $raw = file_get_contents($this->statePath);
        if ($raw === false) {
            throw new RuntimeException("State-File konnte nicht gelesen werden: {$this->statePath}");
        }

        $parsed = json_decode($raw, true);
        if (! is_array($parsed)) {
            throw new RuntimeException("State-File korrupt: {$this->statePath}");
        }

        return [
            'last_counter'     => (int) ($parsed['last_counter'] ?? 0),
            'current_version'  => (string) ($parsed['current_version'] ?? ''),
            'trust_version'    => (int) ($parsed['trust_version'] ?? 0),
            'valid_keys'       => $parsed['valid_keys'] ?? [],
            'revoked_keys'     => $parsed['revoked_keys'] ?? [],
            'trust_expires_at' => (string) ($parsed['trust_expires_at'] ?? ''),
            'last_check'       => $parsed['last_check'] ?? null,
            'updated_at'       => (string) ($parsed['updated_at'] ?? ''),
        ];
    }

    private function writeLastCheck(array &$state, bool $hasUpdate, ?array $manifest = null): void
    {
        $state['last_check'] = [
            'checked_at'     => now()->toIso8601String(),
            'has_update'     => $hasUpdate,
            'latest_version' => $manifest['version'] ?? null,
            'changelog'      => $manifest['changelog'] ?? null,
            'name'           => $manifest['name'] ?? null,
            'description'    => $manifest['description'] ?? null,
        ];
        $this->writeState($state);
    }

    private function writeState(array $state): void
    {
        $this->atomicJsonWrite($this->statePath, $state);
    }

    private function atomicJsonWrite(string $path, array $data): void
    {
        $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('JSON-Encode fehlgeschlagen: ' . json_last_error_msg());
        }

        $dir = dirname($path);
        $this->ensureDirectory($dir, 0700);

        $tmp = $path . '.tmp.' . getmypid();

        if (file_put_contents($tmp, $payload, LOCK_EX) === false) {
            throw new RuntimeException("Temporäre Datei konnte nicht geschrieben werden: {$tmp}");
        }

        if (! rename($tmp, $path)) {
            $this->safeUnlink($tmp);
            throw new RuntimeException("Atomarer Swap fehlgeschlagen: {$tmp} → {$path}");
        }
    }

    // ── Staging Cleanup ─────────────────────────────────

    private function cleanStaging(): void
    {
        if (! is_dir($this->stagingDir)) {
            return;
        }

        $this->recursiveDelete($this->stagingDir);
    }

    private function recursiveDelete(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (! rmdir($item->getPathname())) {
                    Log::warning('schneespur-update: rmdir fehlgeschlagen', ['path' => $item->getPathname()]);
                }
            } else {
                $this->safeUnlink($item->getPathname());
            }
        }

        if (! rmdir($dir)) {
            Log::warning('schneespur-update: rmdir fehlgeschlagen', ['path' => $dir]);
        }
    }

    // ── Helpers ──────────────────────────────────────────

    private function ensureDirectory(string $path, int $mode = 0755): void
    {
        if (is_dir($path)) {
            return;
        }
        if (! mkdir($path, $mode, true) && ! is_dir($path)) {
            throw new RuntimeException("Verzeichnis konnte nicht erstellt werden: {$path}");
        }
    }

    private function safeUnlink(string $path): void
    {
        if (is_file($path) && ! unlink($path)) {
            Log::warning('schneespur-update: unlink fehlgeschlagen', ['path' => $path]);
        }
    }

    private function countFiles(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $_) {
            $count++;
        }

        return $count;
    }

    private function logPhase(string $phase, string $status, array $context = []): void
    {
        $context['phase']  = $phase;
        $context['status'] = $status;
        Log::channel('single')->info("schneespur-update: [{$phase}] {$status}", $context);
    }

    // ── Canonical JSON (unchanged) ──────────────────────

    public static function canonicalJson(array $d): string
    {
        self::sortRecursive($d);
        $j = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($j === false) {
            throw new RuntimeException('json_encode failed: ' . json_last_error_msg());
        }

        return $j;
    }

    private static function sortRecursive(array &$arr): void
    {
        foreach ($arr as &$v) {
            if (is_array($v) && self::isAssoc($v)) {
                self::sortRecursive($v);
            }
        }
        unset($v);

        if (self::isAssoc($arr)) {
            ksort($arr, SORT_STRING);
        }
    }

    private static function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
