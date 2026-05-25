<?php

namespace App\Services\Extension;

use Closure;
use Illuminate\Support\Facades\Route;

class ModuleApiRegistrar
{
    public function routes(string $slug, int $version, Closure $callback): void
    {
        Route::prefix("api/mod/{$slug}/v{$version}")
            ->middleware(["module.api:{$slug}"])
            ->name("module.{$slug}.api.v{$version}.")
            ->group($callback);
    }
}
