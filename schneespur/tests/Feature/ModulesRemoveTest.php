<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Services\SchneespurModuleInstaller;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModulesRemoveTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $baseModulePath;
    private string $childModulePath;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }

        $this->baseModulePath = base_path('modules/base-mod');
        $this->childModulePath = base_path('modules/child-mod');
    }

    protected function tearDown(): void
    {
        foreach ([$this->baseModulePath, $this->childModulePath] as $path) {
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            }
        }

        @unlink(storage_path('app/installed.lock'));
        parent::tearDown();
    }

    private function createModuleOnDisk(string $slug, array $manifest): void
    {
        $path = base_path("modules/{$slug}");
        File::ensureDirectoryExists($path);
        File::put("{$path}/module.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function createModuleRecord(string $slug, bool $enabled = false, string $version = '1.0.0'): Module
    {
        return Module::create([
            'slug' => $slug,
            'version' => $version,
            'enabled' => $enabled,
            'manifest_json' => [],
            'installed_at' => now(),
        ]);
    }

    public function test_remove_blocked_when_active_dependants_exist_without_force(): void
    {
        $this->createModuleOnDisk('base-mod', [
            'name' => 'Base',
            'version' => '1.0.0',
            'requires' => [],
            'conflicts' => [],
        ]);
        $this->createModuleOnDisk('child-mod', [
            'name' => 'Child',
            'version' => '1.0.0',
            'requires' => ['base-mod' => '>=1.0.0'],
            'conflicts' => [],
        ]);

        $this->createModuleRecord('base-mod', enabled: true);
        $this->createModuleRecord('child-mod', enabled: true);

        $this->partialMock(SchneespurModuleInstaller::class);

        $this->artisan('schneespur:modules-remove', ['slug' => 'base-mod'])
            ->assertExitCode(1)
            ->expectsOutputToContain('child-mod');

        $this->assertNotNull(Module::where('slug', 'base-mod')->first());
    }

    public function test_remove_proceeds_with_force_despite_dependants(): void
    {
        $this->createModuleOnDisk('base-mod', [
            'name' => 'Base',
            'version' => '1.0.0',
            'requires' => [],
            'conflicts' => [],
        ]);
        $this->createModuleOnDisk('child-mod', [
            'name' => 'Child',
            'version' => '1.0.0',
            'requires' => ['base-mod' => '>=1.0.0'],
            'conflicts' => [],
        ]);

        $this->createModuleRecord('base-mod', enabled: true);
        $this->createModuleRecord('child-mod', enabled: true);

        $this->partialMock(SchneespurModuleInstaller::class, function ($mock) {
            $mock->shouldReceive('remove')->with('base-mod')->once()->andReturn(true);
        });

        $this->artisan('schneespur:modules-remove', ['slug' => 'base-mod', '--force' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('child-mod');

        $this->assertNull(Module::where('slug', 'base-mod')->first());
    }

    public function test_remove_works_normally_when_no_dependants_exist(): void
    {
        $this->createModuleOnDisk('base-mod', [
            'name' => 'Base',
            'version' => '1.0.0',
            'requires' => [],
            'conflicts' => [],
        ]);

        $this->createModuleRecord('base-mod', enabled: true);

        $this->partialMock(SchneespurModuleInstaller::class, function ($mock) {
            $mock->shouldReceive('remove')->with('base-mod')->once()->andReturn(true);
        });

        $this->artisan('schneespur:modules-remove', ['slug' => 'base-mod', '--force' => true])
            ->assertExitCode(0);

        $this->assertNull(Module::where('slug', 'base-mod')->first());
    }
}
