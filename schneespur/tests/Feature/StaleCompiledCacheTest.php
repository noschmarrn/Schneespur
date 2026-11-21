<?php

namespace Tests\Feature;

use App\Support\StaleCompiledCache;
use Tests\TestCase;

/**
 * Der Updater von 1.2.0/1.2.1 kopiert die neuen Dateien und baut danach im
 * SELBEN Request config:cache und route:cache. Auf Webhosting liefert OPcache
 * dabei noch die alten config/*.php und routes/web.php — die Caches entstehen
 * aus dem alten Code. 1.2.2 lief deshalb durch und lieferte danach 500
 * („Route [admin.settings.update.finalize] not defined“).
 *
 * Erkennung: `app.build` ist ein Literal in config/app.php und kommt damit
 * durch OPcache; VERSION wird per file_get_contents gelesen und nicht.
 */
class StaleCompiledCacheTest extends TestCase
{
    public function test_matching_build_and_version_is_not_stale(): void
    {
        $this->assertFalse(StaleCompiledCache::isStale('1.2.3', "1.2.3\n"));
    }

    public function test_a_cache_built_from_older_code_is_stale(): void
    {
        $this->assertTrue(StaleCompiledCache::isStale('1.2.2', '1.2.3'));
    }

    public function test_a_cache_built_before_the_build_key_existed_is_stale(): void
    {
        $this->assertTrue(StaleCompiledCache::isStale(null, '1.2.3'));
    }

    public function test_an_unreadable_version_file_never_triggers(): void
    {
        $this->assertFalse(StaleCompiledCache::isStale(null, ''));
    }

    public function test_discard_removes_the_given_cache_files(): void
    {
        $dir = sys_get_temp_dir().'/stale-cache-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents("$dir/config.php", '<?php return [];');
        file_put_contents("$dir/routes-v7.php", '<?php');

        StaleCompiledCache::discard(["$dir/config.php", "$dir/routes-v7.php", "$dir/events.php"]);

        $this->assertFileDoesNotExist("$dir/config.php");
        $this->assertFileDoesNotExist("$dir/routes-v7.php");
        rmdir($dir);
    }

    /** Drift-Wächter: wer VERSION hebt, muß app.build mitheben — sonst verwirft jede Installation ständig ihre Caches. */
    public function test_the_build_literal_matches_the_version_file(): void
    {
        $this->assertSame(trim(file_get_contents(base_path('VERSION'))), config('app.build'));
    }
}
