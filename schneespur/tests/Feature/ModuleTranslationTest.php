<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Services\ModuleManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ModuleTranslationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    /**
     * Since M018/S03 the manager is default-deny: a module directory alone does
     * not boot, the persisted row has to say so.
     */
    private function registerModule(string $slug): void
    {
        Module::create([
            'slug' => $slug,
            'version' => '1.0.0',
            'enabled' => true,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);
    }

    private function bootModuleManagerWithExample(): void
    {
        $this->registerModule('example');

        putenv('EXAMPLE_MODULE_ENABLED=true');
        $_ENV['EXAMPLE_MODULE_ENABLED'] = true;

        $manager = new ModuleManager($this->app, base_path('modules'));
        $manager->discover();
        $manager->boot();
    }

    public function test_module_translations_load_in_german(): void
    {
        $this->app->setLocale('de');
        $this->bootModuleManagerWithExample();

        $result = __('example::messages.hello');

        $this->assertSame('Hallo vom Beispiel-Modul!', $result);
    }

    public function test_module_translations_load_in_english(): void
    {
        $this->app->setLocale('en');
        $this->bootModuleManagerWithExample();

        $result = __('example::messages.hello');

        $this->assertSame('Hello from the Example Module!', $result);
    }

    public function test_module_without_lang_directory_boots_without_error(): void
    {
        $tempDir = sys_get_temp_dir() . '/schneespur_test_modules_' . uniqid();
        $moduleDir = $tempDir . '/nolang';
        mkdir($moduleDir, 0755, true);
        file_put_contents($moduleDir . '/module.json', json_encode([
            'name' => 'No Lang Module',
            'version' => '1.0.0',
            'description' => 'Module without lang directory',
        ]));

        $this->registerModule('nolang');

        try {
            $manager = new ModuleManager($this->app, $tempDir);
            $manager->discover();
            $manager->boot();

            $this->assertTrue($manager->isEnabled('nolang'));
        } finally {
            @unlink($moduleDir . '/module.json');
            @rmdir($moduleDir);
            @rmdir($tempDir);
        }
    }

    public function test_unknown_module_translation_returns_key(): void
    {
        $result = __('nonexistent::foo.bar');

        $this->assertSame('nonexistent::foo.bar', $result);
    }
}
