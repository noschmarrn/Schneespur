<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Open-Meteo liefert snow_depth in Metern; gespeichert und angezeigt wird die
 * Höhe in Zentimetern. Bis 1.2.1 wanderte der Meterwert unverändert in die
 * Spalte, so daß 15 cm Schnee im Einsatznachweis als „0,2 cm" erschienen.
 *
 * Nur die Zeilen der beiden Open-Meteo-Anbieter werden gerechnet. Bright Sky
 * und MET Norway haben immer 0.0 geschrieben — mal hundert bleibt das null,
 * aber sie auszulassen macht down() exakt umkehrbar und erfaßt einen von einem
 * Modul registrierten Anbieter nicht versehentlich mit.
 */
return new class extends Migration
{
    private const PROVIDERS = ['openmeteo_free', 'openmeteo_api'];

    public function up(): void
    {
        DB::table('weather_snapshots')
            ->whereIn('provider', self::PROVIDERS)
            ->update(['snow_depth' => DB::raw('snow_depth * 100')]);
    }

    public function down(): void
    {
        // 100.0 statt 100: SQLite gibt einer ganzzahligen Zwischenablage (die
        // NUMERIC-Spaltenaffinität rundet 15.0 zu 15) sonst eine
        // Ganzzahldivision, und 15 / 100 würde zu 0 statt 0.15 abschneiden.
        DB::table('weather_snapshots')
            ->whereIn('provider', self::PROVIDERS)
            ->update(['snow_depth' => DB::raw('snow_depth / 100.0')]);
    }
};
