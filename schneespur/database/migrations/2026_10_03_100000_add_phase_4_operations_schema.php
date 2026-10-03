<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Das Schema der Phase 4 (Betrieb), in einem Durchgang.
 *
 * 1. customers bekommt eine eigene Adresse. Das Sammel-PDF nahm bisher die
 *    des ersten Objekts — bei einer Hausverwaltung systematisch falsch.
 * 2. service_jobs bekommt die interne Notiz und vier Spalten, in denen der
 *    Preis beim Abschluß festgeschrieben wird. Ein leerer Betrag heißt
 *    "unbekannt", nicht 0.
 * 3. customer_object_prices: ein Preis je Objekt und Einsatzart. job_type
 *    ist ein String und kein Fremdschlüssel — Einsatzarten registrieren
 *    Module zur Laufzeit in der JobTypeRegistry.
 *
 * Jede Spalte hat ihren eigenen Wächter und ihr eigenes Schema::table(): ein
 * Blueprint mit zwei Spalten baut zwei ALTER-Statements. Auf Shared Hosting
 * läuft diese Migration in einem Web-Request und kann dazwischen enden; der
 * nächste Aufruf muß dort weitermachen können.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumn('customers', 'street', fn (Blueprint $t) => $t->string('street', 200)->nullable());
        $this->addColumn('customers', 'zip', fn (Blueprint $t) => $t->string('zip', 16)->nullable());
        $this->addColumn('customers', 'city', fn (Blueprint $t) => $t->string('city', 100)->nullable());
        $this->addColumn('customers', 'state', fn (Blueprint $t) => $t->string('state', 64)->nullable());

        $this->addColumn('service_jobs', 'internal_notes', fn (Blueprint $t) => $t->text('internal_notes')->nullable());
        $this->addColumn('service_jobs', 'price_amount_cents', fn (Blueprint $t) => $t->unsignedInteger('price_amount_cents')->nullable());
        $this->addColumn('service_jobs', 'price_unit', fn (Blueprint $t) => $t->string('price_unit', 32)->nullable());
        $this->addColumn('service_jobs', 'price_rate_cents', fn (Blueprint $t) => $t->unsignedInteger('price_rate_cents')->nullable());
        $this->addColumn('service_jobs', 'price_increment_min', fn (Blueprint $t) => $t->unsignedSmallInteger('price_increment_min')->nullable());

        if (! Schema::hasTable('customer_object_prices')) {
            Schema::create('customer_object_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_object_id')->constrained('customer_objects')->cascadeOnDelete();
                $table->string('job_type', 32);
                $table->unsignedInteger('amount_cents');
                $table->string('unit', 32);
                $table->timestamps();
                $table->unique(['customer_object_id', 'job_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_object_prices');

        foreach (['price_increment_min', 'price_rate_cents', 'price_unit', 'price_amount_cents', 'internal_notes'] as $column) {
            $this->dropColumn('service_jobs', $column);
        }

        foreach (['state', 'city', 'zip', 'street'] as $column) {
            $this->dropColumn('customers', $column);
        }
    }

    private function addColumn(string $table, string $column, \Closure $definition): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, $definition);
    }

    private function dropColumn(string $table, string $column): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
    }
};
