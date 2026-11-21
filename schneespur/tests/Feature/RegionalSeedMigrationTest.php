<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Format\RegionPreset;
use App\Services\Format\RegionRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * 2026_09_04_100000_seed_regional_address_settings läuft beim Update im
 * Prozeß der VORVERSION: Der Updater kopiert die neuen Dateien und migriert,
 * ohne neu zu booten. RegionRegistry und RegionPreset sind dort schon die
 * alten, geladenen Klassen. 1.2.1 las `toSettings()['address_format']` aus
 * der 1.2.0-Fassung, die den Schlüssel nicht kennt — jede Installation, die
 * per Knopf aktualisierte, blieb im Wartungsmodus (503).
 *
 * Deshalb darf die Migration für die Core-Regionen nicht von App-Klassen
 * abhängen. Der erste Test simuliert das mit einer leeren Registry.
 */
class RegionalSeedMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const KEYS = ['address_format', 'csv_delimiter', 'week_starts_on'];

    private function loadMigration(): Migration
    {
        return require base_path('database/migrations/2026_09_04_100000_seed_regional_address_settings.php');
    }

    private function prepare(string $region): void
    {
        Setting::whereIn('key', self::KEYS)->delete();
        Setting::set('format_region', $region);
    }

    public function test_a_core_region_is_seeded_without_asking_the_registry(): void
    {
        $this->prepare('us');
        $this->app->instance(RegionRegistry::class, new RegionRegistry);

        $this->loadMigration()->up();

        $this->assertSame('street_city_state_zip', Setting::get('address_format'));
        $this->assertSame(',', Setting::get('csv_delimiter'));
        $this->assertSame('7', Setting::get('week_starts_on'));
    }

    /** Drift-Wächter: die Tabelle in der Migration muß zu den Core-Presets passen. */
    public function test_the_migration_table_matches_every_core_preset(): void
    {
        $registry = app(RegionRegistry::class);

        foreach (['de', 'at', 'ch', 'us', 'ca', 'gb'] as $code) {
            $this->prepare($code);
            $this->app->instance(RegionRegistry::class, new RegionRegistry);

            $this->loadMigration()->up();

            $expected = array_intersect_key($registry->get($code)->toSettings(), array_flip(self::KEYS));
            foreach ($expected as $key => $value) {
                $this->assertSame($value, Setting::get($key), "{$code}: {$key}");
            }

            $this->app->instance(RegionRegistry::class, $registry);
        }
    }

    public function test_a_module_region_is_read_from_the_registry(): void
    {
        $this->prepare('fr');
        app(RegionRegistry::class)->add(new RegionPreset(
            code: 'fr', label: 'France', currencyCode: 'EUR', currencyPosition: 'after',
            numberFormat: 'dot_comma', dateFormat: 'dmy_dot', timeFormat: '24h',
            unitDistance: 'metric', unitTemperature: 'celsius', unitWeight: 'metric',
            addressFormat: 'street_zip_city', csvDelimiter: ';', weekStartsOn: 1,
        ));

        $this->loadMigration()->up();

        $this->assertSame('street_zip_city', Setting::get('address_format'));
        $this->assertSame('1', Setting::get('week_starts_on'));
    }

    public function test_an_unknown_region_leaves_the_settings_alone(): void
    {
        $this->prepare('xx');

        $this->loadMigration()->up();

        foreach (self::KEYS as $key) {
            $this->assertNull(Setting::get($key));
        }
    }

    public function test_a_value_the_operator_set_is_never_overwritten(): void
    {
        $this->prepare('us');
        Setting::set('csv_delimiter', ';');

        $this->loadMigration()->up();

        $this->assertSame(';', Setting::get('csv_delimiter'));
        $this->assertSame('street_city_state_zip', Setting::get('address_format'));
    }
}
