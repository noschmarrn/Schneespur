<?php

namespace Tests\Feature;

use App\Services\Extension\ModuleApiRegistrar;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleApiRouteRegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleApiRegistrar::class)->routes('example', 1, function () {
            Route::get('status', fn () => response()->json(['status' => 'ok']))
                ->name('status');
        });

        Route::getRoutes()->refreshNameLookups();
    }

    public function test_module_api_route_has_correct_prefix(): void
    {
        $route = Route::getRoutes()->getByName('module.example.api.v1.status');

        $this->assertNotNull($route, 'Route module.example.api.v1.status should exist');
        $this->assertSame('api/mod/example/v1/status', $route->uri());
    }

    public function test_module_api_route_has_middleware(): void
    {
        $route = Route::getRoutes()->getByName('module.example.api.v1.status');

        $this->assertNotNull($route, 'Route module.example.api.v1.status should exist');
        $this->assertContains('module.api:example', $route->middleware());
    }
}
