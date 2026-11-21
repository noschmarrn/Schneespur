<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;

/**
 * Verwirft config-, route- und event-Cache, wenn sie von älterem Code gebaut
 * wurden.
 *
 * Der Updater von 1.2.0/1.2.1 kopiert die neuen Dateien und ruft danach im
 * SELBEN Request config:cache und route:cache. Auf Webhosting liefert OPcache
 * dabei noch die alten config/*.php und routes/web.php; die Caches entstehen
 * aus dem alten Code und werden festgeschrieben. 1.2.2 lief deshalb durch und
 * lieferte danach 500 („Route … not defined“), bis jemand die Dateien per FTP
 * löschte.
 *
 * Erkennung: `app.build` ist ein Literal in config/app.php — es kommt mit der
 * PHP-Datei durch OPcache und ist im veralteten Cache alt oder fehlt. VERSION
 * dagegen liest config/app.php per file_get_contents, also immer frisch.
 *
 * Läuft im register() des AppServiceProvider: Die Routen werden erst beim
 * Booten geladen, sie kommen also schon in DIESEM Request frisch aus den
 * Dateien. Die Konfiguration dieses einen Requests bleibt die alte; ab dem
 * nächsten liest Laravel sie aus den Dateien.
 */
final class StaleCompiledCache
{
    public static function discardIfBuiltByOlderCode(Application $app): bool
    {
        if (! $app->configurationIsCached()) {
            return false;
        }

        $onDisk = (string) @file_get_contents($app->basePath('VERSION'));
        $built = config('app.build');

        if (! self::isStale(is_string($built) ? $built : null, $onDisk)) {
            return false;
        }

        self::discard([
            $app->getCachedConfigPath(),
            $app->getCachedRoutesPath(),
            $app->getCachedEventsPath(),
        ]);

        // Der nächste Request soll die neuen PHP-Dateien sehen, nicht die
        // Bytecodes, aus denen der alte Updater die Caches gebaut hat.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        Log::warning('schneespur: veraltete Laravel-Caches verworfen', [
            'cached_build' => $built,
            'version'      => trim($onDisk),
        ]);

        return true;
    }

    public static function isStale(?string $cachedBuild, string $versionOnDisk): bool
    {
        $version = trim($versionOnDisk);

        // Ohne lesbare VERSION läßt sich nichts vergleichen — dann lieber mit
        // dem Cache weiterlaufen als ihn bei jedem Request wegzuwerfen.
        if ($version === '') {
            return false;
        }

        return $cachedBuild !== $version;
    }

    /** @param list<string> $files */
    public static function discard(array $files): void
    {
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
