<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlowThresholdMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migration(): object
    {
        return require base_path('database/migrations/2026_09_04_110000_add_state_and_unit_aware_plow_threshold.php');
    }

    private function customerId(): int
    {
        return Customer::create(['name' => 'Test Customer'])->id;
    }

    public function test_up_moves_an_existing_centimetre_value_across(): void
    {
        $m = $this->migration();

        // Zurück in den Zustand vor 1.2.1 …
        $m->down();
        $this->assertTrue(Schema::hasColumn('customer_objects', 'plow_threshold_cm'));

        $id = DB::table('customer_objects')->insertGetId([
            'customer_id' => $this->customerId(),
            'name' => 'Altbestand',
            'plow_threshold_cm' => 5,
            'notify_recipients' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // … und wieder vorwärts.
        $m->up();

        $row = DB::table('customer_objects')->find($id);

        $this->assertSame(5.0, (float) $row->plow_threshold_value);
        $this->assertSame('cm', $row->plow_threshold_unit);
        $this->assertFalse(Schema::hasColumn('customer_objects', 'plow_threshold_cm'));
        $this->assertTrue(Schema::hasColumn('customer_objects', 'state'));
    }

    public function test_up_leaves_a_row_without_a_threshold_empty(): void
    {
        $m = $this->migration();
        $m->down();

        $id = DB::table('customer_objects')->insertGetId([
            'customer_id' => $this->customerId(),
            'name' => 'Ohne Schwelle',
            'plow_threshold_cm' => null,
            'notify_recipients' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $m->up();

        $row = DB::table('customer_objects')->find($id);

        $this->assertNull($row->plow_threshold_value);
        // Keine Einheit ohne Wert: 'cm' hier stünde für eine Angabe, die
        // niemand gemacht hat.
        $this->assertNull($row->plow_threshold_unit);
    }

    public function test_down_converts_inches_back_to_whole_centimetres(): void
    {
        $id = DB::table('customer_objects')->insertGetId([
            'customer_id' => $this->customerId(),
            'name' => 'US-Objekt',
            'plow_threshold_value' => 2.00,
            'plow_threshold_unit' => 'in',
            'notify_recipients' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->down();

        // 2 in = 5,08 cm, gerundet 5. Der Verlust ist gewollt und dokumentiert.
        $this->assertSame(5, (int) DB::table('customer_objects')->find($id)->plow_threshold_cm);
    }

    public function test_down_clamps_a_value_the_old_column_cannot_hold(): void
    {
        $id = DB::table('customer_objects')->insertGetId([
            'customer_id' => $this->customerId(),
            'name' => 'Sehr hohe Schwelle',
            'plow_threshold_value' => 300.00,
            'plow_threshold_unit' => 'cm',
            'notify_recipients' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->migration()->down();

        // unsignedTinyInteger endet bei 255. Ohne Klemme wirft MySQL hier und
        // SQLite schriebe still etwas Falsches.
        $this->assertSame(255, (int) DB::table('customer_objects')->find($id)->plow_threshold_cm);
    }

    public function test_up_is_repeatable_after_a_broken_run(): void
    {
        // Shared Hosting ohne CLI: der Web-Request kann mitten in der Migration
        // enden. Ein zweiter Aufruf darf dann nicht an "column already exists"
        // scheitern.
        $this->migration()->up();
        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('customer_objects', 'plow_threshold_value'));
    }

    public function test_the_accessor_converts_both_units_to_millimetres(): void
    {
        $object = CustomerObject::create([
            'customer_id' => $this->customerId(),
            'name' => 'Zoll-Objekt',
            'plow_threshold_value' => 2.00,
            'plow_threshold_unit' => 'in',
        ]);

        // 2 in = 50,8 mm, exakt und ohne Zwischenrundung über Zentimeter.
        $this->assertSame(50.8, $object->plowThresholdMm());

        $metric = CustomerObject::create([
            'customer_id' => $this->customerId(),
            'name' => 'cm-Objekt',
            'plow_threshold_value' => 5.10,
            'plow_threshold_unit' => 'cm',
        ]);

        $this->assertSame(51.0, $metric->plowThresholdMm());
    }

    public function test_the_accessor_is_null_without_a_threshold(): void
    {
        $object = CustomerObject::create([
            'customer_id' => $this->customerId(),
            'name' => 'Ohne Schwelle',
        ]);

        // Nicht 0.0: "keine Schwelle gesetzt" heißt nicht "räume ab 0 mm".
        // Dieselbe Unterscheidung, die Phase 2 für die Schneehöhe erzwang.
        $this->assertNull($object->plowThresholdMm());
    }
}
