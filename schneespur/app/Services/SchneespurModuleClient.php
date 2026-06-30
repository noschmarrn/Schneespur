<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SchneespurModuleClient
{
    private string $serverUrl;
    private string $collectionSlug;
    private string $catalogEndpoint;
    private int $timeout;
    private int $downloadTimeout;
    private string $stateFilePath;

    public function __construct()
    {
        $this->serverUrl       = rtrim(config('schneespur_modules.server_url'), '/');
        $this->collectionSlug  = config('schneespur_modules.collection_slug');
        $this->catalogEndpoint = config('schneespur_modules.catalog_endpoint');
        $this->timeout         = (int) config('schneespur_modules.timeout', 10);
        $this->downloadTimeout = (int) config('schneespur_modules.download_timeout', 120);
        $this->stateFilePath   = config('schneespur_modules.state_file_path');
    }

    /**
     * Fetch the module catalog from the server.
     *
     * Returns normalized catalog array. On 304 returns the cached normalized
     * catalog from state (or re-fetches without If-None-Match if no cache).
     * Returns null only when there is no cache and the server-side state
     * cannot be reconstructed. Throws on HTTP error.
     */
    public function fetchCatalog(): ?array
    {
        $state    = $this->loadState();
        $etag     = $state['catalog_etag'] ?? null;
        $cached   = $state['catalog_cache'] ?? null;

        $url = $this->serverUrl . str_replace('{slug}', $this->collectionSlug, $this->catalogEndpoint);

        $request = Http::acceptJson()->timeout($this->timeout);

        // Only send If-None-Match when we actually have a cached body to fall
        // back on — otherwise a 304 leaves us with nothing to display.
        if ($etag && $cached !== null) {
            $request = $request->withHeaders(['If-None-Match' => $etag]);
        }

        $response = $request->get($url);

        if ($response->status() === 304) {
            Log::info('schneespur-modules: catalog not modified (304)');
            $state['synced_at'] = now()->toIso8601String();
            $this->writeState($state);

            return $cached;
        }

        if ($response->status() === 404) {
            Log::error('schneespur-modules: catalog fetch failed — collection not found (404)');
            throw new RuntimeException(
                'Module-Collection nicht gefunden (HTTP 404). '
                . 'Ist der Collection-Slug "' . $this->collectionSlug . '" korrekt?'
            );
        }

        if ($response->failed()) {
            Log::error('schneespur-modules: catalog fetch failed', [
                'http_status' => $response->status(),
            ]);
            throw new RuntimeException("Katalog-Fetch fehlgeschlagen: HTTP {$response->status()}");
        }

        $raw = $response->json();
        if (! is_array($raw) || ! array_key_exists('modules', $raw)) {
            throw new RuntimeException('Katalog-Response hat unerwartete Form');
        }

        $catalog = [
            'collection' => $raw['collection'] ?? null,
            'modules'    => array_map(fn ($m) => $this->normalizeModule($m), $raw['modules']),
        ];

        $newEtag = $response->header('ETag');
        $state['catalog_etag']  = $newEtag ?: ($state['catalog_etag'] ?? null);
        $state['catalog_cache'] = $catalog;
        $state['synced_at']     = now()->toIso8601String();
        $this->writeState($state);

        $moduleCount = count($catalog['modules']);
        Log::info('schneespur-modules: catalog fetched', ['module_count' => $moduleCount]);

        return $catalog;
    }

    /**
     * Map a server-side module entry to the internal shape expected by the
     * admin controller and views. The server may evolve its field names
     * independently — this is the single place where that drift is bridged.
     */
    private function normalizeModule(array $raw): array
    {
        $appLocale = app()->getLocale();
        $primary   = $raw['primary_locale'] ?? $appLocale;

        $category = $raw['category'] ?? null;
        if (is_array($category)) {
            $category = self::i18nPick($category, $primary);
        }

        $normalized = [
            'slug'                 => $raw['slug'] ?? null,
            'name'                 => $raw['name'] ?? [],
            'description'          => $raw['description'] ?? [],
            'version'              => $raw['current_version'] ?? $raw['version'] ?? null,
            'category'             => $category,
            'image'                => $raw['image_url'] ?? $raw['image'] ?? null,
            'download_url'         => $raw['download_url'] ?? null,
            'sha256'               => $raw['sha256'] ?? null,
            'size_bytes'           => $raw['size_bytes'] ?? null,
            'requires_permissions' => $raw['requires_permissions'] ?? [],
            'info_url'             => $raw['info_url'] ?? null,
            'primary_locale'       => $primary,
        ];

        // The catalog does not carry a trust_level; leave it unset rather than
        // forcing a misleading "community" classification on official modules.
        if (isset($raw['trust_level'])) {
            $normalized['trust_level'] = $raw['trust_level'];
        } else {
            $normalized['trust_level'] = null;
        }

        if (isset($raw['signature'])) {
            $normalized['signature'] = $raw['signature'];
        }
        if (isset($raw['key_id'])) {
            $normalized['key_id'] = $raw['key_id'];
        }

        return $normalized;
    }

    /**
     * Download a module ZIP, verify size and SHA256.
     *
     * Returns the temp file path on success.
     * Throws on size/hash mismatch or HTTP error.
     */
    public function downloadModule(string $slug, string $url, string $expectedSha256, int $expectedSize): string
    {
        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException("Download-URL muss HTTPS sein: {$url}");
        }

        // Pin the download host to the configured module server. The catalog
        // response (and thus download_url) is not cryptographically bound to
        // the server, so an off-host URL would be a blind-SSRF / arbitrary-fetch
        // primitive. All legitimate downloads live on the module server host.
        $urlHost = parse_url($url, PHP_URL_HOST);
        $expectedHost = parse_url($this->serverUrl, PHP_URL_HOST);
        if (! is_string($urlHost) || strcasecmp($urlHost, (string) $expectedHost) !== 0) {
            throw new RuntimeException(
                "Download-URL-Host nicht erlaubt: {$urlHost} (erwartet {$expectedHost})"
            );
        }

        Log::info('schneespur-modules: download started', ['slug' => $slug]);

        $tmp = tempnam(sys_get_temp_dir(), 'schneespur-mod-');

        $response = Http::timeout($this->downloadTimeout)
            ->withOptions(['sink' => $tmp])
            ->get($url);

        if ($response->failed()) {
            $this->safeUnlink($tmp);
            Log::error('schneespur-modules: download failed', [
                'slug'        => $slug,
                'http_status' => $response->status(),
            ]);
            throw new RuntimeException("Modul-Download fehlgeschlagen: HTTP {$response->status()} für {$slug}");
        }

        clearstatcache(true, $tmp);
        $actualSize = filesize($tmp);
        if ($actualSize !== $expectedSize) {
            $this->safeUnlink($tmp);
            Log::error('schneespur-modules: size mismatch', [
                'slug'     => $slug,
                'expected' => $expectedSize,
                'actual'   => $actualSize,
            ]);
            throw new RuntimeException(
                "Größe stimmt nicht für {$slug}: {$actualSize} vs erwartet {$expectedSize}"
            );
        }

        $actualSha256 = hash_file('sha256', $tmp);
        if (! hash_equals($expectedSha256, $actualSha256)) {
            $this->safeUnlink($tmp);
            Log::error('schneespur-modules: SHA256 mismatch', [
                'slug'     => $slug,
                'expected' => $expectedSha256,
                'actual'   => $actualSha256,
            ]);
            throw new RuntimeException(
                "SHA256 stimmt nicht für {$slug}: {$actualSha256} vs erwartet {$expectedSha256}"
            );
        }

        Log::info('schneespur-modules: SHA256 verified', ['slug' => $slug]);

        return $tmp;
    }

    /**
     * Pick the best locale value from an i18n dict.
     *
     * Fallback chain: app locale → primaryLocale → 'de' → first non-empty.
     */
    public static function i18nPick(array $field, string $primaryLocale): string
    {
        $appLocale = app()->getLocale();
        foreach ([$appLocale, $primaryLocale, 'de'] as $loc) {
            if (! empty($field[$loc])) {
                return $field[$loc];
            }
        }

        foreach ($field as $v) {
            if (! empty($v)) {
                return $v;
            }
        }

        return '';
    }

    // ── State Persistence ─────────────────────────────────

    public function loadState(): array
    {
        if (! is_file($this->stateFilePath)) {
            return [
                'catalog_etag'     => null,
                'catalog_cache'    => null,
                'synced_at'        => null,
                'installed'        => [],
                'orphans'          => [],
                'trust_version'    => 0,
                'valid_keys'       => [],
                'revoked_keys'     => [],
                'trust_expires_at' => '',
            ];
        }

        $raw = file_get_contents($this->stateFilePath);
        if ($raw === false) {
            throw new RuntimeException("State-File konnte nicht gelesen werden: {$this->stateFilePath}");
        }

        $parsed = json_decode($raw, true);
        if (! is_array($parsed)) {
            throw new RuntimeException("State-File korrupt: {$this->stateFilePath}");
        }

        return [
            'catalog_etag'     => $parsed['catalog_etag'] ?? null,
            'catalog_cache'    => $parsed['catalog_cache'] ?? null,
            'synced_at'        => $parsed['synced_at'] ?? null,
            'installed'        => $parsed['installed'] ?? [],
            'orphans'          => $parsed['orphans'] ?? [],
            'trust_version'    => (int) ($parsed['trust_version'] ?? 0),
            'valid_keys'       => $parsed['valid_keys'] ?? [],
            'revoked_keys'     => $parsed['revoked_keys'] ?? [],
            'trust_expires_at' => (string) ($parsed['trust_expires_at'] ?? ''),
        ];
    }

    public function writeState(array $state): void
    {
        $this->atomicJsonWrite($this->stateFilePath, $state);
    }

    private function atomicJsonWrite(string $path, array $data): void
    {
        $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new RuntimeException('JSON-Encode fehlgeschlagen: ' . json_last_error_msg());
        }

        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Verzeichnis konnte nicht erstellt werden: {$dir}");
        }

        $tmp = $path . '.tmp.' . getmypid();

        if (file_put_contents($tmp, $payload, LOCK_EX) === false) {
            throw new RuntimeException("Temporäre Datei konnte nicht geschrieben werden: {$tmp}");
        }

        if (! rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Atomarer Swap fehlgeschlagen: {$tmp} → {$path}");
        }
    }

    private function safeUnlink(string $path): void
    {
        if (is_file($path) && ! unlink($path)) {
            Log::warning('schneespur-modules: unlink fehlgeschlagen', ['path' => $path]);
        }
    }
}
