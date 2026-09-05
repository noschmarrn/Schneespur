<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Format\Formatter;
use App\Services\PhotoAnnotationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Die Bildunterschrift ist die einzige Ausgabe der App, die sich nicht mehr
 * korrigieren läßt: PhotoService::store() ruft annotate() bei jedem
 * hochgeladenen Einsatzfoto, und der Text steht danach in den Pixeln der
 * gespeicherten JPEG-Datei. Eine falsch geordnete Adresse ist dort kein
 * Schönheitsfehler, sondern unwiderruflich.
 *
 * Deshalb wird hier die Adreßzeile selbst geprüft und nicht das fertige Bild:
 * aus dem JPEG ließe sie sich nur über eine Texterkennung zurückgewinnen.
 */
class PhotoAnnotationAddressTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function job(array $objectOverrides = []): Job
    {
        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create(['name' => 'Summit Snow']);

        $object = CustomerObject::create(array_merge([
            'customer_id' => $customer->id,
            'name' => 'Parkplatz',
            'street' => '400 S 4th St',
            'zip' => '55401',
            'city' => 'Minneapolis',
            'state' => 'MN',
        ], $objectOverrides));

        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => '2026-01-15 08:00:00',
            'ended_at' => '2026-01-15 10:00:00',
        ]);

        return Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => '2026-01-15 08:00:00',
            'ended_at' => '2026-01-15 10:00:00',
        ]);
    }

    /** Die Zeile, die mit "Kunde: " beginnt — die einzige mit einer Adresse. */
    private function customerLine(Job $job, string $addressFormat): string
    {
        Setting::set('address_format', $addressFormat);
        app()->forgetInstance(Formatter::class);

        $method = new \ReflectionMethod(PhotoAnnotationService::class, 'buildMetadataLines');
        $lines = $method->invoke(app(PhotoAnnotationService::class), $job);

        foreach ($lines as $line) {
            if (str_starts_with($line, 'Kunde: ')) {
                return $line;
            }
        }

        $this->fail('Die Bildunterschrift enthält keine Kundenzeile');
    }

    public function test_a_german_caption_prints_zip_before_city(): void
    {
        $job = $this->job([
            'street' => 'Innstraße 12',
            'zip' => '94032',
            'city' => 'Passau',
            'state' => 'Bayern',
        ]);

        $this->assertSame(
            'Kunde: Summit Snow, Innstraße 12, 94032 Passau (Parkplatz)',
            $this->customerLine($job, 'street_zip_city')
        );
    }

    /**
     * Der eigentliche Fehler: bis 1.2.1 bekam ein US-Betrieb
     * "400 S 4th St, 55401 Minneapolis" ins Bild gebrannt — deutsche
     * Reihenfolge, und der Bundesstaat fehlte ganz.
     */
    public function test_a_us_caption_prints_city_state_zip(): void
    {
        $job = $this->job();

        $line = $this->customerLine($job, 'street_city_state_zip');

        $this->assertSame(
            'Kunde: Summit Snow, 400 S 4th St, Minneapolis, MN 55401 (Parkplatz)',
            $line
        );

        // Ausdrücklich, weil genau das die alte Form produzierte: die
        // Postleitzahl darf nicht mehr vor dem Ort stehen.
        $this->assertStringNotContainsString('55401 Minneapolis', $line);
    }

    /**
     * Ohne Adresse darf kein hängendes und kein doppeltes Komma
     * stehenbleiben — die alte Form hängte jedes Teilstück mit einem eigenen
     * ", " an.
     */
    public function test_an_object_without_an_address_leaves_no_dangling_comma(): void
    {
        $job = $this->job([
            'name' => 'Summit Snow',
            'street' => null,
            'zip' => null,
            'city' => null,
            'state' => null,
        ]);

        $this->assertSame('Kunde: Summit Snow', $this->customerLine($job, 'street_zip_city'));
    }
}
