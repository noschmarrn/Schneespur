<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\CsvExportService;
use App\Services\Format\Formatter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CsvDelimiterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function seedOneJob(): void
    {
        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create(['name' => 'Summit Snow']);

        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Parking lot',
            'street' => '400 S 4th St',
            'zip' => '55401',
            'city' => 'Minneapolis',
            'state' => 'MN',
        ]);

        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => '2026-01-15 08:00:00',
            'ended_at' => '2026-01-15 10:00:00',
        ]);

        Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => '2026-01-15 08:00:00',
            'ended_at' => '2026-01-15 10:00:00',
        ]);
    }

    private function csv(string $delimiter): string
    {
        Setting::set('csv_delimiter', $delimiter);
        app()->forgetInstance(Formatter::class);

        return app(CsvExportService::class)->buildCsv('all', null, null);
    }

    public function test_a_german_export_uses_semicolons(): void
    {
        $this->seedOneJob();

        $csv = $this->csv(';');

        // "Summit Snow" enthält ein Leerzeichen: fputcsv() (unter League\Csv)
        // umschließt das Feld deshalb mit Anführungszeichen, unabhängig vom
        // Trennzeichen. Die schließende Anführung steht also vor dem
        // Semikolon, nicht der Feldinhalt selbst.
        $this->assertStringContainsString('"Summit Snow";', $csv);
    }

    /**
     * Der eigentliche Fehler: mit Semikolon kippt Excel im englischen
     * Sprachraum jede Zeile in eine einzige Spalte. Der Export war dort nicht
     * unschön, sondern unbrauchbar.
     */
    public function test_a_us_export_uses_commas(): void
    {
        $this->seedOneJob();

        $csv = $this->csv(',');

        $this->assertStringContainsString('"Summit Snow",', $csv);
        $this->assertStringNotContainsString('Summit Snow;', $csv);
    }

    public function test_the_state_has_its_own_column(): void
    {
        $this->seedOneJob();

        $csv = $this->csv(';');

        $this->assertStringContainsString(__('export.csv_col_object_state'), $csv);
        $this->assertStringContainsString('MN', $csv);
    }
}
