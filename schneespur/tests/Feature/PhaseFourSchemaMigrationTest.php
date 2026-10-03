<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseFourSchemaMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const JOB_COLUMNS = [
        'internal_notes', 'price_amount_cents', 'price_unit', 'price_rate_cents', 'price_increment_min',
    ];

    private const CUSTOMER_COLUMNS = ['street', 'zip', 'city', 'state'];

    private function migration(): object
    {
        return require base_path('database/migrations/2026_10_03_100000_add_phase_4_operations_schema.php');
    }

    private function assertFullyUp(): void
    {
        foreach (self::JOB_COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('service_jobs', $column), "service_jobs.$column fehlt");
        }
        foreach (self::CUSTOMER_COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('customers', $column), "customers.$column fehlt");
        }
        $this->assertTrue(Schema::hasTable('customer_object_prices'));
    }

    private function assertFullyDown(): void
    {
        foreach (self::JOB_COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('service_jobs', $column), "service_jobs.$column steht noch");
        }
        foreach (self::CUSTOMER_COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('customers', $column), "customers.$column steht noch");
        }
        $this->assertFalse(Schema::hasTable('customer_object_prices'));
    }

    public function test_down_and_up_round_trip(): void
    {
        $m = $this->migration();

        $m->down();
        $this->assertFullyDown();

        $m->up();
        $this->assertFullyUp();
    }

    /**
     * Der Web-Request stirbt nach der ersten Spalte jeder Tabelle. Der zweite
     * Aufruf muß dort weitermachen, statt an "duplicate column" zu scheitern.
     * Der Teilzustand wird von Hand hergestellt: unter LazilyRefreshDatabase
     * ist die Datenbank beim Teststart vollständig migriert, ein zweites up()
     * allein setzte kein einziges Statement ab.
     */
    public function test_up_resumes_from_a_half_finished_run(): void
    {
        $m = $this->migration();
        $m->down();

        Schema::table('customers', fn (Blueprint $t) => $t->string('street', 200)->nullable());
        Schema::table('service_jobs', fn (Blueprint $t) => $t->text('internal_notes')->nullable());

        $m->up();

        $this->assertFullyUp();
    }

    public function test_down_resumes_from_a_half_finished_run(): void
    {
        $m = $this->migration();

        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('state'));
        Schema::table('service_jobs', fn (Blueprint $t) => $t->dropColumn('price_increment_min'));
        Schema::dropIfExists('customer_object_prices');

        $m->down();

        $this->assertFullyDown();
    }

    public function test_a_job_type_can_only_have_one_price_row_per_object(): void
    {
        $customer = \App\Models\Customer::create(['name' => 'Test Customer']);
        $object = \App\Models\CustomerObject::create(['customer_id' => $customer->id, 'name' => 'Objekt']);

        \App\Models\CustomerObjectPrice::create([
            'customer_object_id' => $object->id, 'job_type' => 'raumen', 'amount_cents' => 8500, 'unit' => 'per_job',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \App\Models\CustomerObjectPrice::create([
            'customer_object_id' => $object->id, 'job_type' => 'raumen', 'amount_cents' => 9000, 'unit' => 'per_job',
        ]);
    }
}
