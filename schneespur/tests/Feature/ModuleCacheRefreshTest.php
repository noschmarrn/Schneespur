<?php

namespace Tests\Feature;

use App\Services\ModuleCacheRefresher;
use App\Services\SchneespurModuleInstaller;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleCacheRefreshTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $modulePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->modulePath = base_path('modules/refresh-mod');
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->modulePath)) {
            File::deleteDirectory($this->modulePath);
        }
        parent::tearDown();
    }

    public function test_refresh_clears_compiled_caches(): void
    {
        Artisan::shouldReceive('call')->once()->with('view:clear');
        Artisan::shouldReceive('call')->once()->with('config:clear');
        Artisan::shouldReceive('call')->once()->with('route:clear');
        Artisan::shouldReceive('call')->once()->with('event:clear');

        app(ModuleCacheRefresher::class)->refresh();
    }

    public function test_installer_refreshes_caches_after_remove(): void
    {
        File::ensureDirectoryExists($this->modulePath);
        File::put("{$this->modulePath}/module.json", '{"name":"Refresh","version":"1.0.0"}');

        $refresher = $this->mock(ModuleCacheRefresher::class);
        $refresher->shouldReceive('refresh')->once();

        $result = app(SchneespurModuleInstaller::class)->remove('refresh-mod');

        $this->assertTrue($result);
    }

    public function test_installer_does_not_refresh_when_remove_fails(): void
    {
        $refresher = $this->mock(ModuleCacheRefresher::class);
        $refresher->shouldReceive('refresh')->never();

        // Module directory does not exist → remove returns false, no refresh.
        $result = app(SchneespurModuleInstaller::class)->remove('does-not-exist-mod');

        $this->assertFalse($result);
    }
}
