<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\GeocodingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyStateAndGeocodingTest extends TestCase
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

    /** @param array<string, string> $overrides */
    private function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Summit Snow',
            'company_street' => '400 S 4th St',
            'company_zip' => '55401',
            'company_city' => 'Minneapolis',
            'company_state' => 'MN',
            'season_from' => '11-01',
            'season_to' => '03-31',
            'alert_overdue_hours' => 4,
            'default_locale' => 'de',
            'display_timezone' => 'America/Chicago',
        ], $overrides);
    }

    public function test_the_state_is_saved(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

        $this->actingAs($this->admin())
            ->post('/admin/settings/company', $this->companyPayload())
            ->assertSessionHasNoErrors();

        $this->assertSame('MN', Setting::get('company_state'));
    }

    public function test_the_state_reaches_nominatim(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '44.9778', 'lon' => '-93.2650'],
            ], 200),
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/settings/company', $this->companyPayload());

        // "Springfield" ohne Bundesstaat ist in den USA mehrdeutig — deshalb
        // führt Nominatim den Parameter in seiner strukturierten Abfrage.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'state=MN'));
    }

    public function test_an_empty_state_is_not_sent_at_all(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '48.5667', 'lon' => '13.4319'],
            ], 200),
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/settings/company', $this->companyPayload([
                'company_street' => 'Innstraße 12',
                'company_zip' => '94032',
                'company_city' => 'Passau',
                'company_state' => '',
            ]));

        // Ein leerer state-Parameter verschlechtert die Trefferqualität, statt
        // sie zu verbessern.
        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'state='));
    }

    public function test_the_service_still_works_with_three_arguments(): void
    {
        // Der Vertrag gegenüber Modulen: der neue Parameter steht am Ende und
        // ist optional.
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '48.5667', 'lon' => '13.4319'],
            ], 200),
        ]);

        $result = app(GeocodingService::class)->resolve('Innstraße 12', '94032', 'Passau');

        $this->assertSame(48.5667, $result['lat']);
    }

    public function test_the_failure_message_no_longer_promises_manual_coordinates(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

        $this->actingAs($this->admin())
            ->post('/admin/settings/company', $this->companyPayload())
            ->assertSessionHas('warning');

        // Unter Betrieb & Standort gibt es keine Koordinatenfelder, und der
        // Anzeigeblock ist im Fehlerfall ohnehin leer (company.blade.php:80
        // rendert ihn nur, wenn company_lat gesetzt ist).
        foreach (['de', 'en'] as $locale) {
            app()->setLocale($locale);

            $this->assertStringNotContainsStringIgnoringCase(
                'manuell',
                __('settings.company_geocode_fail')
            );
            $this->assertStringNotContainsStringIgnoringCase(
                'manually',
                __('settings.company_geocode_fail')
            );
            $this->assertStringNotContainsStringIgnoringCase(
                'manuell',
                __('settings.company_geocode_manual_hint')
            );
            $this->assertStringNotContainsStringIgnoringCase(
                'manually',
                __('settings.company_geocode_manual_hint')
            );
        }
    }
}
