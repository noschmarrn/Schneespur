<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Format\RegionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The Docker/Coolify path never runs the installer wizard — there this
 * migration IS the decision. 1.1.9 shipped exactly this bug for the product
 * name: an English container ran under the German brand.
 */
class FormatSeedingTest extends TestCase
{
    use LazilyRefreshDatabase;

    // Named seedRegion(), not seed(): the base TestCase already declares a
    // public seed() (Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase)
    // for database seeders, and a private override of it is a fatal error.
    private function seedRegion(string $locale): void
    {
        Setting::whereIn('key', ['format_region', 'currency_code', 'date_format'])->delete();

        config(['app.locale' => $locale]);

        $preset = app(RegionRegistry::class)->get($locale === 'de' ? 'de' : 'us');

        foreach ($preset->toSettings() as $key => $value) {
            Setting::set($key, $value);
        }
    }

    /**
     * Loads the migration's anonymous class by requiring its file directly —
     * the standard way to get a callable instance out of a
     * `return new class extends Migration` file outside of `artisan migrate`.
     */
    private function loadMigration(): Migration
    {
        return require base_path('database/migrations/2026_08_07_000001_seed_format_settings.php');
    }

    public function test_a_german_install_gets_german_formats(): void
    {
        $this->seedRegion('de');

        $this->assertSame('de', Setting::get('format_region'));
        $this->assertSame('EUR', Setting::get('currency_code'));
        $this->assertSame('dmy_dot', Setting::get('date_format'));
    }

    public function test_an_english_install_does_not_get_euro(): void
    {
        $this->seedRegion('en');

        $this->assertNotSame('EUR', Setting::get('currency_code'));
        $this->assertSame('mdy_slash', Setting::get('date_format'));
    }

    public function test_the_migration_does_not_overwrite_an_existing_choice(): void
    {
        Setting::set('format_region', 'ch');

        // Re-running the migration body must leave a configured install alone.
        $this->artisan('migrate', ['--force' => true]);

        $this->assertSame('ch', Setting::get('format_region'));
    }

    /**
     * The test above passes trivially under LazilyRefreshDatabase: the suite
     * has already migrated before the test body runs, so `artisan migrate`
     * finds nothing pending and does nothing at all — the assertion would
     * pass whether or not the guard exists. This test instead invokes the
     * migration's up() directly, so it genuinely exercises the guard clause.
     */
    public function test_migration_up_does_not_overwrite_an_existing_value_directly(): void
    {
        Setting::set('format_region', 'ch');
        config(['app.locale' => 'de']);

        $this->loadMigration()->up();

        $this->assertSame('ch', Setting::get('format_region'));
    }

    public function test_migration_up_fills_missing_settings_directly(): void
    {
        Setting::whereIn('key', ['format_region', 'currency_code', 'date_format'])->delete();
        config(['app.locale' => 'de']);

        $this->loadMigration()->up();

        $this->assertSame('de', Setting::get('format_region'));
        $this->assertSame('EUR', Setting::get('currency_code'));
        $this->assertSame('dmy_dot', Setting::get('date_format'));
    }
}
