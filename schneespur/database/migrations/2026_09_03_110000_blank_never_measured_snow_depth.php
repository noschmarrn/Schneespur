<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bright Sky (DWD) und MET Norway melden keine Schneehöhe. Beide schrieben
 * bis 1.2.1 fest 0.0 in die Spalte — und 0,0 cm heißt nicht „unbekannt",
 * sondern „kein Schnee". Der Kunde eines deutschen Bright-Sky-Betreibers las
 * damit in jedem Einsatznachweis „0,0 cm Schnee", auch mitten im
 * Schneetreiben. Für einen Nachweis ist still falsch schlimmer als leer.
 *
 * Die Vorgänger-Migration (2026_09_03_100000) hat diese beiden Anbieter
 * ausdrücklich ausgelassen, weil mal hundert an einer Null nichts ändert;
 * hier werden sie nachgeholt. Es geht keine Messung verloren: eine gemessene
 * Null hat es bei diesen Anbietern nie gegeben, sie melden die Größe gar
 * nicht. Die Bedingung auf 0 läßt einen von einem Modul unter demselben Slug
 * geschriebenen echten Wert dennoch stehen.
 *
 * Anbieter mit echter Schneehöhe (die beiden Open-Meteo-Reihen) bleiben
 * unberührt: dort ist eine gemessene 0.0 ein Nachweis für sich — beim
 * Streudienst ist die Abwesenheit von Schnee der Punkt.
 */
return new class extends Migration
{
    private const PROVIDERS_WITHOUT_SNOW_DEPTH = ['brightsky', 'met_norway'];

    public function up(): void
    {
        DB::table('weather_snapshots')
            ->whereIn('provider', self::PROVIDERS_WITHOUT_SNOW_DEPTH)
            ->where('snow_depth', 0)
            ->update(['snow_depth' => null]);
    }

    public function down(): void
    {
        DB::table('weather_snapshots')
            ->whereIn('provider', self::PROVIDERS_WITHOUT_SNOW_DEPTH)
            ->whereNull('snow_depth')
            ->update(['snow_depth' => 0]);
    }
};
