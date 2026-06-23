<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Makes a module file change take effect without a server restart.
 *
 * On shared/managed hosting the admin cannot restart php-fpm, yet after a
 * module update the old code keeps running because:
 *   - PHP's OPcache still serves the previous bytecode of the module's PHP
 *     files (classes, service providers, and .php translation files), and
 *   - Laravel may serve a cached config/route/view if the app was "optimized".
 *
 * Clearing the compiled caches and resetting OPcache from within the request
 * that performed the update reuses the running php-fpm pool, so the next
 * request reads the freshly written module files.
 */
class ModuleCacheRefresher
{
    public function refresh(): void
    {
        foreach (['view:clear', 'config:clear', 'route:clear', 'event:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (\Throwable $e) {
                Log::warning("schneespur-modules: cache refresh '{$command}' failed: {$e->getMessage()}");
            }
        }

        // The key step for hosts where a restart isn't possible: drop the stale
        // module bytecode. Guarded because opcache may be disabled or its API
        // restricted; a failure here must never break the module operation.
        if (function_exists('opcache_reset') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOL)) {
            try {
                opcache_reset();
            } catch (\Throwable $e) {
                Log::warning("schneespur-modules: opcache_reset failed: {$e->getMessage()}");
            }
        }
    }
}
