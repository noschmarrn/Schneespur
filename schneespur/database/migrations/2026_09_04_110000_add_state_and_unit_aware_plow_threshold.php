<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Die beiden Schemaänderungen der Phase 3, in einem Durchgang.
 *
 * 1. `state` — Bundesstaat, Provinz oder Kanton. Ohne dieses Feld ist eine
 *    US-Adresse nicht schreibbar: "Minneapolis 55401" ist unvollständig.
 *
 * 2. Die Räumschwelle wird Wert plus Einheit. `plow_threshold_cm` war
 *    unsignedTinyInteger — ganze Zentimeter bis 255. Damit ist 2 Zoll
 *    (5,08 cm) nicht eintragbar, und der Typ müßte ohnehin fallen. Gespeichert
 *    wird jetzt, was der Betrieb eingegeben hat, damit ein US-Betrieb 2″ als
 *    2″ zurückliest statt als 5,08 cm.
 *
 * Der Bestand wandert als 'cm': er *wurde* in Zentimetern eingegeben, gleich
 * welche Region heute eingestellt ist.
 *
 * Drei getrennte Schema-Blöcke, weil SQLite die Tabelle neu baut, sobald eine
 * Spalte fällt — anlegen, kopieren und löschen in einem Aufruf zu bündeln
 * setzt die Kopie in einen Rebuild hinein.
 *
 * Jeder Block prüft vorher, ob er noch etwas zu tun hat. Auf Shared Hosting
 * läuft diese Migration in einem Web-Request und kann mitten drin enden; der
 * nächste Aufruf muß dort weitermachen können, wo der erste aufhörte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customer_objects', 'state')) {
            Schema::table('customer_objects', function (Blueprint $table) {
                $table->string('state', 64)->nullable()->after('city');
            });
        }

        if (! Schema::hasColumn('customer_objects', 'plow_threshold_value')) {
            Schema::table('customer_objects', function (Blueprint $table) {
                // 5,2 trägt 999,99 — mehr als jede reale Räumschwelle, in
                // Zentimetern wie in Zoll.
                $table->decimal('plow_threshold_value', 5, 2)->nullable();
                $table->string('plow_threshold_unit', 4)->nullable();
            });
        }

        if (Schema::hasColumn('customer_objects', 'plow_threshold_cm')) {
            DB::table('customer_objects')
                ->whereNotNull('plow_threshold_cm')
                ->update([
                    'plow_threshold_value' => DB::raw('plow_threshold_cm'),
                    'plow_threshold_unit' => 'cm',
                ]);

            Schema::table('customer_objects', function (Blueprint $table) {
                $table->dropColumn('plow_threshold_cm');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customer_objects', 'plow_threshold_cm')) {
            Schema::table('customer_objects', function (Blueprint $table) {
                $table->unsignedTinyInteger('plow_threshold_cm')->nullable();
            });
        }

        if (Schema::hasColumn('customer_objects', 'plow_threshold_value')) {
            // In PHP statt in SQL: LEAST() und MIN() heißen in MySQL und SQLite
            // verschieden, und die Rundung soll in beiden Datenbanken dieselbe
            // Zahl ergeben. Gechunkt, weil das hier ohne CLI läuft.
            DB::table('customer_objects')
                ->whereNotNull('plow_threshold_value')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $centimetres = $row->plow_threshold_unit === 'in'
                            ? (float) $row->plow_threshold_value * 2.54
                            : (float) $row->plow_threshold_value;

                        // Der Rückweg verliert die Nachkommastelle: 2 in werden
                        // zu 5 cm und kommen nicht als 2 in zurück. Das ist der
                        // Preis des alten Typs, nicht ein Fehler hier.
                        DB::table('customer_objects')
                            ->where('id', $row->id)
                            ->update(['plow_threshold_cm' => min(255, (int) round($centimetres))]);
                    }
                });

            Schema::table('customer_objects', function (Blueprint $table) {
                $table->dropColumn(['plow_threshold_value', 'plow_threshold_unit']);
            });
        }

        if (Schema::hasColumn('customer_objects', 'state')) {
            Schema::table('customer_objects', function (Blueprint $table) {
                $table->dropColumn('state');
            });
        }
    }
};
