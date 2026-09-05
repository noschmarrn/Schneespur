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
use Illuminate\Support\Facades\App;
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
        $this->assertStringNotContainsString('"Summit Snow";', $csv);
    }

    /**
     * fromSettings() schreibt `(string) (Setting::get($key) ?? $fallback)` —
     * ein leer gespeicherter Wert überlebt diesen Rückfall als ''. date() und
     * time() fangen das mit einem eigenen `??` ab; csvDelimiter() tat es
     * nicht, und League\Csv::setDelimiter('') wirft InvalidArgument: aus dem
     * Export wurde eine 500er-Seite. Über die Oberfläche ist das nicht
     * erreichbar (Rule::in([';', ','])), über ein Modul mit eigenem
     * RegionPreset schon.
     */
    public function test_an_empty_delimiter_falls_back_to_the_semicolon(): void
    {
        $this->seedOneJob();

        $csv = $this->csv('');

        $this->assertStringContainsString('"Summit Snow";', $csv);
        $this->assertSame(';', app(Formatter::class)->csvDelimiter());
    }

    public function test_the_state_has_its_own_column(): void
    {
        $this->seedOneJob();

        $csv = $this->csv(';');

        // Literaltext statt bloß __() gegen sich selbst: fehlte der Schlüssel
        // in beiden Sprachdateien, gäbe __() auf beiden Seiten den rohen
        // Schlüssel zurück und die Zusicherung bliebe trotzdem grün.
        $this->assertStringContainsString(__('export.csv_col_object_state'), $csv);
        $this->assertStringContainsString('Objekt-Bundesland / Kanton', $csv);
        $this->assertStringContainsString('MN', $csv);

        // Die App läuft standardmäßig unter 'de' (config/app.php), das löst
        // direkt auf und berührt den 'en'-Fallback nie. Ohne diese zweite
        // Zusicherung könnte lang/en/export.php den Schlüssel verlieren, ohne
        // daß es ein Test hier bemerkt — ein US-Betrieb bekäme dann den rohen
        // Schlüssel als Spaltenkopf.
        App::setLocale('en');
        $csvEn = $this->csv(';');

        $this->assertStringContainsString('Site State / Province', $csvEn);
    }
}
