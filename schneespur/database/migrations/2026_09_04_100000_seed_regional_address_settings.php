<?php

use App\Models\Setting;
use App\Services\Format\RegionRegistry;
use Illuminate\Database\Migrations\Migration;

/**
 * Die drei regionalen Schlüssel aus 1.2.1 für Installationen nachtragen, die
 * vor diesem Release eingerichtet wurden.
 *
 * 2026_08_07_000001_seed_format_settings kann das nicht: sie ist dort längst
 * gelaufen und läuft nicht erneut. Ohne diese Migration blieben address_format,
 * csv_delimiter und week_starts_on für immer leer — der Formatter fiele auf
 * seine deutschen Vorgabewerte zurück, und ein US-Betrieb, der seine Region
 * einmal gewählt hat, bekäme sie trotzdem nie.
 *
 * Die Quelle ist deshalb die *gespeicherte* Region, nicht APP_LOCALE: der
 * Betrieb hat seine Wahl bereits getroffen. Fehlt sie, bleibt es bei den
 * Vorgabewerten des Formatters — dann hat die Installation auch sonst keine
 * Formateinstellungen und wird von der älteren Seed-Migration bedient.
 *
 * Wie dort gilt: ein Wert, den ein Betrieb gesetzt hat, wird nie überschrieben.
 *
 * Seit 1.2.2 hängt die Migration für die Core-Regionen an keiner App-Klasse.
 * Sie läuft beim Update im Prozeß der VORVERSION: Der Updater kopiert die
 * neuen Dateien und migriert, ohne neu zu booten, und RegionRegistry/
 * RegionPreset sind dort die alten, schon geladenen Klassen. In 1.2.1 las sie
 * `toSettings()['address_format']` aus der 1.2.0-Fassung, die den Schlüssel
 * nicht kennt — jede Installation, die per Knopf aktualisierte, blieb im
 * Wartungsmodus (503). Die Werte stehen deshalb hier; der Drift-Wächter
 * RegionalSeedMigrationTest hält sie mit registerCoreRegions() gleich.
 * Nur eine Modulregion wird über die Registry gelesen, und dort defensiv.
 */
return new class extends Migration
{
    private const NEW_KEYS = ['address_format', 'csv_delimiter', 'week_starts_on'];

    /** Wie AppServiceProvider::registerCoreRegions(), Stand 1.2.1. */
    private const CORE = [
        'de' => ['street_zip_city', ';', '1'],
        'at' => ['street_zip_city', ';', '1'],
        'ch' => ['street_zip_city', ';', '1'],
        'us' => ['street_city_state_zip', ',', '7'],
        'ca' => ['street_city_state_zip', ',', '7'],
        'gb' => ['street_city_zip', ',', '1'],
    ];

    public function up(): void
    {
        $code = Setting::get('format_region');

        if (! is_string($code) || $code === '') {
            return;
        }

        $settings = isset(self::CORE[$code])
            ? array_combine(self::NEW_KEYS, self::CORE[$code])
            : $this->fromModulePreset($code);

        foreach ($settings as $key => $value) {
            if ($value === null || Setting::get($key) !== null) {
                continue;
            }

            Setting::set($key, $value);
        }
    }

    /**
     * Eine Modulregion kennt nur die Registry. Im Prozeß einer Vorversion
     * fehlen dem Preset die drei Schlüssel womöglich — dann bleibt es bei den
     * Vorgabewerten des Formatters, wie vor 1.2.1.
     *
     * @return array<string, ?string>
     */
    private function fromModulePreset(string $code): array
    {
        $preset = app(RegionRegistry::class)->get($code);

        if ($preset === null) {
            return []; // Region eines Moduls, das gerade nicht geladen ist
        }

        $all = $preset->toSettings();

        return array_map(fn (string $key) => $all[$key] ?? null, array_combine(self::NEW_KEYS, self::NEW_KEYS));
    }

    public function down(): void
    {
        Setting::whereIn('key', self::NEW_KEYS)->delete();
    }
};
