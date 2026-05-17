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
     * Returns parsed catalog array on 200, null on 304 (not modified).
     * Throws on HTTP error.
     */
    public function fetchCatalog(): ?array
    {
        $state = $this->loadState();
        $etag  = $state['catalog_etag'] ?? null;

        $url = $this->serverUrl . str_replace('{slug}', $this->collectionSlug, $this->catalogEndpoint);

        $request = Http::acceptJson()->timeout($this->timeout);

        if ($etag) {
            $request = $request->withHeaders(['If-None-Match' => $etag]);
        }

        $response = $request->get($url);

        if ($response->status() === 304) {
            Log::info('schneespur-modules: catalog not modified (304)');
            $state['synced_at'] = now()->toIso8601String();
            $this->writeState($state);

            return null;
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

        $catalog = $response->json();
        if (! is_array($catalog) || ! array_key_exists('modules', $catalog)) {
            throw new RuntimeException('Katalog-Response hat unerwartete Form');
        }

        $newEtag = $response->header('ETag');
        $state['catalog_etag'] = $newEtag ?: ($state['catalog_etag'] ?? null);
        $state['synced_at']    = now()->toIso8601String();
        $this->writeState($state);

        $moduleCount = count($catalog['modules']);
        Log::info('schneespur-modules: catalog fetched', ['module_count' => $moduleCount]);

        return $catalog;
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
                'catalog_etag' => null,
                'synced_at'    => null,
                'installed'    => [],
                'orphans'      => [],
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
            'catalog_etag' => $parsed['catalog_etag'] ?? null,
            'synced_at'    => $parsed['synced_at'] ?? null,
            'installed'    => $parsed['installed'] ?? [],
            'orphans'      => $parsed['orphans'] ?? [],
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
