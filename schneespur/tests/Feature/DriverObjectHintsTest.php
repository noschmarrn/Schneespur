<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\User;
use App\Services\JobLifecycleService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesFormatFixtures;
use Tests\TestCase;

/**
 * Die Hinweise lagen immer am Objekt und erreichten den Fahrer nie. Geprüft
 * wird die gerenderte Seite, nicht ein Dienst: der Defekt war, daß kein View
 * die Daten las.
 */
class DriverObjectHintsTest extends TestCase
{
    use CreatesFormatFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFormatFixtures();
    }

    protected function tearDown(): void
    {
        $this->tearDownFormatFixtures();
        parent::tearDown();
    }

    private function driver(): User
    {
        $user = User::create([
            'name' => 'Driver User',
            'email' => 'hints-driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->role = UserRole::Driver;
        $user->dsgvo_informed_at = now();
        $user->confirmed_version = 99;
        $user->save();

        return $user->fresh();
    }

    private function object(array $overrides = []): CustomerObject
    {
        $customer = Customer::create(['name' => 'Hausverwaltung Donaublick']);

        return CustomerObject::create(array_merge([
            'customer_id' => $customer->id,
            'name' => 'Wohnanlage',
            'access_notes' => 'Hoftor Schlüssel Nr. 14, Torcode 2408',
            'site_notes' => 'Kein Salz auf dem Innenhofpflaster',
            'salt_enabled' => false,
            'plow_threshold_value' => 5,
            'plow_threshold_unit' => 'cm',
        ], $overrides));
    }

    public function test_the_embedded_object_list_carries_the_four_hint_fields(): void
    {
        $this->object();

        $response = $this->actingAs($this->driver())->get(route('driver.home'));

        $response->assertOk();
        $response->assertSee('Torcode 2408', false);
        $response->assertSee('Kein Salz auf dem Innenhofpflaster', false);
        $response->assertSee('"salt_enabled":false', false);
        $response->assertSee('"plow_threshold":'.json_encode(format_snow_depth(5.0), 15), false);
    }

    public function test_an_object_without_a_threshold_embeds_null_not_a_zero(): void
    {
        $this->object(['plow_threshold_value' => null, 'plow_threshold_unit' => null]);

        $response = $this->actingAs($this->driver())->get(route('driver.home'));

        $response->assertSee('"plow_threshold":null', false);
    }

    /**
     * Der Kasten steht an zwei Stellen. Ein Kunde mit nur einem Objekt hat
     * keinen Objekt-Auswähler — der Kasten hängt deshalb an selectedHints,
     * nicht am Auswähler.
     */
    public function test_the_hint_box_is_rendered_before_the_start_and_in_the_running_job(): void
    {
        $this->object();

        $response = $this->actingAs($this->driver())->get(route('driver.home'));

        $response->assertSee('data-object-hints="selectedHints"', false);
        $response->assertSee('data-object-hints="activeHints"', false);
        $response->assertSee(__('driver.hints_title'));
        $response->assertSee(__('driver.hints_no_salt'));
    }

    public function test_the_active_job_names_its_object_so_the_box_can_find_the_hints(): void
    {
        $object = $this->object();
        $driver = $this->driver();

        $service = app(JobLifecycleService::class);
        $service->startShift($driver);
        $service->startJob($driver, $object, 'raumen');

        $this->actingAs($driver)
            ->getJson(route('driver.job.active'))
            ->assertOk()
            ->assertJsonPath('job.object_id', $object->id);
    }
}
