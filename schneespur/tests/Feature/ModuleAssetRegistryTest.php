<?php

namespace Tests\Feature;

use App\Services\Extension\ModuleAssetRegistry;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ModuleAssetRegistryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/module-asset-test-' . uniqid();
        mkdir($this->tmpDir . '/dist', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupDir($this->tmpDir);
        parent::tearDown();
    }

    private function cleanupDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/{,.}*', GLOB_BRACE) as $f) {
            if (basename($f) === '.' || basename($f) === '..') {
                continue;
            }
            is_dir($f) ? $this->cleanupDir($f) : unlink($f);
        }
        rmdir($dir);
    }

    public function test_singleton_resolves(): void
    {
        $a = $this->app->make(ModuleAssetRegistry::class);
        $b = $this->app->make(ModuleAssetRegistry::class);

        $this->assertSame($a, $b);
    }

    public function test_register_assets_parses_css_and_js(): void
    {
        file_put_contents($this->tmpDir . '/dist/manifest.json', json_encode([
            ['type' => 'css', 'file' => 'style.abc.css'],
            ['type' => 'js', 'file' => 'app.def.js'],
        ]));
        file_put_contents($this->tmpDir . '/dist/style.abc.css', '');
        file_put_contents($this->tmpDir . '/dist/app.def.js', '');

        $registry = new ModuleAssetRegistry();
        $registry->registerAssets('demo', $this->tmpDir);

        $this->assertSame(['/modules/demo/style.abc.css'], $registry->getCss());
        $this->assertSame(['/modules/demo/app.def.js'], $registry->getJs());
        $this->assertCount(2, $registry->all());
    }

    public function test_missing_manifest_logs_debug_and_skips(): void
    {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'no manifest.json'));

        $registry = new ModuleAssetRegistry();
        $registry->registerAssets('nomanifest', $this->tmpDir);

        $this->assertEmpty($registry->all());
    }

    public function test_missing_dist_file_logs_warning(): void
    {
        file_put_contents($this->tmpDir . '/dist/manifest.json', json_encode([
            ['type' => 'css', 'file' => 'ghost.css'],
        ]));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'asset file not found'));
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $registry = new ModuleAssetRegistry();
        $registry->registerAssets('broken', $this->tmpDir);

        $this->assertEmpty($registry->getCss());
    }

    public function test_invalid_manifest_json_logs_warning(): void
    {
        file_put_contents($this->tmpDir . '/dist/manifest.json', 'not json');

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'invalid manifest.json'));

        $registry = new ModuleAssetRegistry();
        $registry->registerAssets('bad', $this->tmpDir);

        $this->assertEmpty($registry->all());
    }

    public function test_unknown_asset_type_logs_warning(): void
    {
        file_put_contents($this->tmpDir . '/dist/manifest.json', json_encode([
            ['type' => 'wasm', 'file' => 'module.wasm'],
        ]));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'unknown asset type'));
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $registry = new ModuleAssetRegistry();
        $registry->registerAssets('exotic', $this->tmpDir);

        $this->assertEmpty($registry->all());
    }

    public function test_multiple_modules_aggregate(): void
    {
        $tmpDir2 = sys_get_temp_dir() . '/module-asset-test2-' . uniqid();
        mkdir($tmpDir2 . '/dist', 0755, true);

        file_put_contents($this->tmpDir . '/dist/manifest.json', json_encode([
            ['type' => 'css', 'file' => 'a.css'],
        ]));
        file_put_contents($this->tmpDir . '/dist/a.css', '');

        file_put_contents($tmpDir2 . '/dist/manifest.json', json_encode([
            ['type' => 'css', 'file' => 'b.css'],
            ['type' => 'js', 'file' => 'b.js'],
        ]));
        file_put_contents($tmpDir2 . '/dist/b.css', '');
        file_put_contents($tmpDir2 . '/dist/b.js', '');

        $registry = new ModuleAssetRegistry();
        $registry->registerAssets('mod-a', $this->tmpDir);
        $registry->registerAssets('mod-b', $tmpDir2);

        $this->assertCount(2, $registry->getCss());
        $this->assertCount(1, $registry->getJs());
        $this->assertSame(['/modules/mod-a/a.css', '/modules/mod-b/b.css'], $registry->getCss());

        $this->cleanupDir($tmpDir2);
    }
}
