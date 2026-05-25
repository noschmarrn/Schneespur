<?php

namespace App\Services\Extension;

use Illuminate\Support\Facades\Log;

class ModuleAssetRegistry
{
    protected array $assets = [];

    public function registerAssets(string $slug, string $modulePath): void
    {
        $manifestPath = rtrim($modulePath, '/') . '/dist/manifest.json';

        if (! file_exists($manifestPath)) {
            Log::debug('ModuleAssetRegistry: no manifest.json found', ['slug' => $slug, 'path' => $manifestPath]);
            return;
        }

        $json = file_get_contents($manifestPath);
        if ($json === false) {
            Log::warning('ModuleAssetRegistry: could not read manifest.json', ['slug' => $slug]);
            return;
        }

        $entries = json_decode($json, true);
        if (! is_array($entries)) {
            Log::warning('ModuleAssetRegistry: invalid manifest.json', ['slug' => $slug]);
            return;
        }

        $distPath = rtrim($modulePath, '/') . '/dist';

        foreach ($entries as $entry) {
            if (! is_array($entry) || empty($entry['file']) || empty($entry['type'])) {
                Log::warning('ModuleAssetRegistry: skipping invalid manifest entry', ['slug' => $slug, 'entry' => $entry]);
                continue;
            }

            if (! in_array($entry['type'], ['css', 'js'], true)) {
                Log::warning('ModuleAssetRegistry: unknown asset type', ['slug' => $slug, 'type' => $entry['type']]);
                continue;
            }

            $filePath = $distPath . '/' . $entry['file'];
            if (! file_exists($filePath)) {
                Log::warning('ModuleAssetRegistry: asset file not found in dist/', [
                    'slug' => $slug,
                    'file' => $entry['file'],
                    'expected' => $filePath,
                ]);
                continue;
            }

            $url = '/modules/' . $slug . '/' . $entry['file'];

            $this->assets[] = [
                'type' => $entry['type'],
                'url' => $url,
                'slug' => $slug,
            ];
        }

        Log::debug('ModuleAssetRegistry: assets registered', [
            'slug' => $slug,
            'count' => count(array_filter($this->assets, fn ($a) => $a['slug'] === $slug)),
        ]);
    }

    /** @return string[] */
    public function getCss(): array
    {
        return array_values(array_map(
            fn ($a) => $a['url'],
            array_filter($this->assets, fn ($a) => $a['type'] === 'css')
        ));
    }

    /** @return string[] */
    public function getJs(): array
    {
        return array_values(array_map(
            fn ($a) => $a['url'],
            array_filter($this->assets, fn ($a) => $a['type'] === 'js')
        ));
    }

    public function all(): array
    {
        return $this->assets;
    }
}
