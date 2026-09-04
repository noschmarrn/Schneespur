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
 */
return new class extends Migration
{
    private const NEW_KEYS = ['address_format', 'csv_delimiter', 'week_starts_on'];

    public function up(): void
    {
        $code = Setting::get('format_region');

        if (! is_string($code) || $code === '') {
            return;
        }

        $preset = app(RegionRegistry::class)->get($code);

        if ($preset === null) {
            return; // Region eines Moduls, das gerade nicht geladen ist
        }

        $settings = $preset->toSettings();

        foreach (self::NEW_KEYS as $key) {
            if (Setting::get($key) !== null) {
                continue;
            }

            Setting::set($key, $settings[$key]);
        }
    }

    public function down(): void
    {
        Setting::whereIn('key', self::NEW_KEYS)->delete();
    }
};
