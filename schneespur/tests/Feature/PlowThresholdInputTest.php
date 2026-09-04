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

    public function test_an_empty_threshold_stores_neither_value_nor_unit(): void
    {
        $this->region(['unit_distance' => 'metric']);

        $customer = Customer::create(['name' => 'Kunde']);

        $this->actingAs($this->admin())
            ->post("/admin/customers/{$customer->id}/objects", [
                'name' => 'Ohne Schwelle',
                'plow_threshold' => '',
                'notify_recipients' => 'customer',
            ])
            ->assertSessionHasNoErrors();

        $object = CustomerObject::where('name', 'Ohne Schwelle')->firstOrFail();

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
}
