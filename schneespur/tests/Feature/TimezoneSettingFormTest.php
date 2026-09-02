<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Support\Timezones;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TimezoneSettingFormTest extends TestCase
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
        $u = User::create(['name' => 'A', 'email' => 'a@test.local', 'password' => Hash::make('password')]);
        $u->role = UserRole::Admin;
        $u->save();

        return $u->fresh();
    }

    /**
     * The list has to be the one PHP ships, not a hand-kept excerpt. The
     * hand-kept excerpt in the setup wizard is precisely why an American
     * installation silently kept Europe/Berlin: the wizard offers to detect
     * the browser's zone but only applies one that is already an option, and
     * America/Chicago was not among the six European entries.
     */
    public function test_the_list_covers_the_zones_the_hand_kept_one_missed(): void
    {
        $flat = [];
        foreach (Timezones::grouped() as $zones) {
            $flat += $zones;
        }

        foreach (['America/Chicago', 'America/New_York', 'America/Vancouver', 'Europe/Oslo', 'Europe/Berlin', 'UTC'] as $zone) {
            $this->assertArrayHasKey($zone, $flat, "missing {$zone}");
        }
    }

    public function test_labels_carry_the_current_offset(): void
    {
        $grouped = Timezones::grouped();

        $this->assertMatchesRegularExpression(
            '/^Europe\/Berlin \(UTC[+-]\d{2}:\d{2}\)$/',
            $grouped['Europe']['Europe/Berlin']
        );
    }

    public function test_the_company_form_offers_the_timezone_field(): void
    {
        Setting::set('display_timezone', 'America/Chicago');

        $this->actingAs($this->admin())
            ->get(route('admin.settings.company'))
            ->assertOk()
            ->assertSee('name="display_timezone"', false)
            ->assertSee('value="America/Chicago" selected', false);
    }

    public function test_saving_a_new_timezone_sticks(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.company.update'), $this->validPayload(['display_timezone' => 'America/Denver']))
            ->assertRedirect();

        $this->assertSame('America/Denver', Setting::get('display_timezone'));
    }

    /**
     * A rejected value must not be written. Formatter::formatMoment() would
     * throw on it, on every page carrying a date.
     */
    public function test_an_invalid_timezone_is_refused(): void
    {
        Setting::set('display_timezone', 'Europe/Berlin');

        $this->actingAs($this->admin())
            ->post(route('admin.settings.company.update'), $this->validPayload(['display_timezone' => 'Mars/Olympus_Mons']))
            ->assertSessionHasErrors('display_timezone');

        $this->assertSame('Europe/Berlin', Setting::get('display_timezone'));
    }

    /**
     * The other required fields of this form have to ride along, or validation
     * fails for an unrelated reason and the test proves nothing at all. That is
     * the trap that made the first season test green without testing anything.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Testbetrieb',
            'season_from' => '11-01',
            'season_to' => '03-31',
            'alert_overdue_hours' => '4',
            'default_locale' => 'de',
            'display_timezone' => 'Europe/Berlin',
        ], $overrides);
    }
}
