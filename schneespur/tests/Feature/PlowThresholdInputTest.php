<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Setting;
use App\Models\User;
use App\Services\Format\Formatter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlowThresholdInputTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));

        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('admin');

        return $user->fresh();
    }

    private function region(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }

        app()->forgetInstance(Formatter::class);
    }

    public function test_the_snow_unit_follows_the_distance_setting(): void
    {
        $this->region(['unit_distance' => 'imperial']);
        $this->assertSame('in', app(Formatter::class)->snowUnit());

        $this->region(['unit_distance' => 'metric']);
        $this->assertSame('cm', app(Formatter::class)->snowUnit());
    }

    public function test_a_german_operator_enters_a_decimal_with_a_comma(): void
    {
        $this->region(['unit_distance' => 'metric', 'number_format' => 'dot_comma']);

        $customer = Customer::create(['name' => 'Kunde']);

        $this->actingAs($this->admin())
            ->post("/admin/customers/{$customer->id}/objects", [
                'name' => 'Hofeinfahrt',
                'plow_threshold' => '5,1',
                'notify_recipients' => 'customer',
            ])
            ->assertSessionHasNoErrors();

        $object = CustomerObject::where('name', 'Hofeinfahrt')->firstOrFail();

        $this->assertSame(5.1, (float) $object->plow_threshold_value);
        $this->assertSame('cm', $object->plow_threshold_unit);
    }

    /**
     * Der eigentliche Anlaß der ganzen Umstellung: 2 Zoll waren nicht
     * eintragbar, weil das alte Feld ganze Zentimeter erzwang.
     */
    public function test_a_us_operator_enters_two_inches_and_gets_two_inches_back(): void
    {
        $this->region(['unit_distance' => 'imperial', 'number_format' => 'comma_dot']);

        $customer = Customer::create(['name' => 'Customer']);

        $this->actingAs($this->admin())
            ->post("/admin/customers/{$customer->id}/objects", [
                'name' => 'Parking lot',
                'plow_threshold' => '2',
                'notify_recipients' => 'customer',
            ])
            ->assertSessionHasNoErrors();

        $object = CustomerObject::where('name', 'Parking lot')->firstOrFail();

        $this->assertSame(2.0, (float) $object->plow_threshold_value);
        $this->assertSame('in', $object->plow_threshold_unit);
        $this->assertSame(50.8, $object->plowThresholdMm());
    }

    /**
     * Gibt der Nullbehandlung Zähne: eine frisch angelegte Zeile ist ohnehin
     * null (beide Spalten sind nullable, ohne Default), also kann ein Test,
     * der nur anlegt, gar nicht scheitern, egal ob validated() die Schlüssel
     * überhaupt anfaßt. Erst das Löschen eines zuvor gesetzten Werts über den
     * Update-Pfad beweist, daß ein leeres Feld die Spalten aktiv auf null
     * setzt statt sie unberührt zu lassen.
     */
    public function test_clearing_a_previously_set_threshold_nulls_both_columns_on_update(): void
    {
        $this->region(['unit_distance' => 'metric']);

        $customer = Customer::create(['name' => 'Kunde']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Mit Schwelle',
            'plow_threshold_value' => 5,
            'plow_threshold_unit' => 'cm',
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/customers/{$customer->id}/objects/{$object->id}", [
                'name' => 'Mit Schwelle',
                'plow_threshold' => '',
                'notify_recipients' => 'customer',
            ])
            ->assertSessionHasNoErrors();

        $object->refresh();

        $this->assertNull($object->plow_threshold_value);
        $this->assertNull($object->plow_threshold_unit);
    }

    public function test_a_non_numeric_threshold_is_rejected(): void
    {
        $this->region(['unit_distance' => 'metric', 'number_format' => 'dot_comma']);

        $customer = Customer::create(['name' => 'Kunde']);

        $this->actingAs($this->admin())
            ->post("/admin/customers/{$customer->id}/objects", [
                'name' => 'Unsinn',
                'plow_threshold' => 'viel',
                'notify_recipients' => 'customer',
            ])
            ->assertSessionHasErrors('plow_threshold');
    }

    public function test_the_form_label_names_the_operators_own_unit(): void
    {
        $this->region(['unit_distance' => 'imperial']);

        $customer = Customer::create(['name' => 'Customer']);

        $this->actingAs($this->admin())
            ->get("/admin/customers/{$customer->id}/objects/create")
            ->assertSee('(in)', escape: false)
            ->assertDontSee('(cm)', escape: false);
    }

    /**
     * I1: ein Objekt wurde in Zoll angelegt, der Betrieb ist seither auf
     * metrisch umgestellt. Das Bearbeiten-Formular muß den gespeicherten
     * Wert in die jetzt gültige Einheit umrechnen (2 in -> 5,08 cm) statt
     * die rohe Spalte unter der neuen Beschriftung anzuzeigen — sonst
     * stimmt Beschriftung und Zahl nicht überein, und ein unverändertes
     * erneutes Speichern würde 50 mm zu 127 mm machen.
     */
    public function test_the_edit_form_converts_the_stored_threshold_into_the_current_unit(): void
    {
        $this->region(['unit_distance' => 'imperial', 'number_format' => 'comma_dot']);

        $customer = Customer::create(['name' => 'Customer']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Parking lot',
            'plow_threshold_value' => 2,
            'plow_threshold_unit' => 'in',
        ]);

        $this->region(['unit_distance' => 'metric', 'number_format' => 'dot_comma']);

        $this->actingAs($this->admin())
            ->get("/admin/customers/{$customer->id}/objects/{$object->id}/edit")
            ->assertSee('5,08', escape: false)
            ->assertDontSee('value="2"', escape: false);
    }

    /**
     * I2: 999,99 ist der dokumentierte Höchstwert (decimal(5,2)). Bei nur
     * einer Nachkommastelle würde er als "1.000,0" angezeigt, und ein
     * unverändertes erneutes Absenden schlüge fehl — an einem Wert, den das
     * System selbst ins Feld geschrieben hat. Zwei Nachkommastellen bei der
     * Anzeige machen den Rückweg verlustfrei.
     */
    public function test_the_edit_form_round_trips_full_precision_without_mutation(): void
    {
        $this->region(['unit_distance' => 'metric', 'number_format' => 'dot_comma']);

        $customer = Customer::create(['name' => 'Kunde']);
        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Präzise',
            'plow_threshold_value' => 999.99,
            'plow_threshold_unit' => 'cm',
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get("/admin/customers/{$customer->id}/objects/{$object->id}/edit")
            ->assertSee('999,99', escape: false)
            ->assertDontSee('1.000,0', escape: false);

        $this->actingAs($admin)
            ->put("/admin/customers/{$customer->id}/objects/{$object->id}", [
                'name' => 'Präzise',
                'plow_threshold' => '999,99',
                'notify_recipients' => 'customer',
            ])
            ->assertSessionHasNoErrors();

        $object->refresh();

        $this->assertSame(999.99, (float) $object->plow_threshold_value);
    }
}
