<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanySeasonFormTest extends TestCase
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
     * Every month must be able to come back selected. The months 10 to 12 were
     * the ones that failed: PHP turns '10', '11', '12' into integer array keys,
     * so the strict comparison against the string never matched.
     */
    public function test_every_stored_season_start_month_is_preselected(): void
    {
        $admin = $this->admin();

        foreach (['01', '02', '09', '10', '11', '12'] as $month) {
            Setting::set('season_from', $month.'-01');

            $response = $this->actingAs($admin)->get(route('admin.settings.company'));

            $response->assertOk();
            $response->assertSee('value="'.$month.'-01" selected', false);
        }
    }

    public function test_season_end_options_never_offer_a_day_that_does_not_exist(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.settings.company'));

        $response->assertOk();
        $response->assertDontSee('value="11-31"', false);
        $response->assertDontSee('value="04-31"', false);
        $response->assertDontSee('value="06-31"', false);
        $response->assertDontSee('value="09-31"', false);
        $response->assertDontSee('value="02-31"', false);
        $response->assertSee('value="11-30"', false);
    }

    public function test_a_season_crossing_the_turn_of_the_year_survives_a_save(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.company.update'), [
            'company_name' => 'Bergblick',
            'season_from' => '11-01',
            'season_to' => '03-31',
            // Also required by the controller's validation, unrelated to the
            // season bug under test, but omitting them fails validation and
            // the request never reaches Setting::set() at all.
            'alert_overdue_hours' => 4,
            'default_locale' => 'de',
        ]);

        $this->assertSame('11-01', Setting::get('season_from'));
        $this->assertSame('03-31', Setting::get('season_to'));
    }
}
