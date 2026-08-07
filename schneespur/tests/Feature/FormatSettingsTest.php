<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FormatSettingsTest extends TestCase
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

    public function test_the_page_renders(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/settings/format')
            ->assertStatus(200);
    }

    public function test_applying_a_region_fills_every_field(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/format', ['apply_region' => 'us'])
            ->assertRedirect();

        $this->assertSame('us', Setting::get('format_region'));
        $this->assertSame('USD', Setting::get('currency_code'));
        $this->assertSame('mdy_slash', Setting::get('date_format'));
        $this->assertSame('12h', Setting::get('time_format'));
        $this->assertSame('imperial', Setting::get('unit_distance'));
        $this->assertSame('fahrenheit', Setting::get('unit_temperature'));
        $this->assertSame('imperial', Setting::get('unit_weight'));
    }

    /** The whole point of having individual fields as well as a template. */
    public function test_a_single_field_can_be_changed_after_a_region_was_applied(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/settings/format', ['apply_region' => 'us']);

        $this->actingAs($admin)->post('/admin/settings/format', [
            'currency_code' => 'USD',
            'currency_position' => 'before',
            'number_format' => 'comma_dot',
            'date_format' => 'mdy_slash',
            'time_format' => '12h',
            'unit_distance' => 'metric',
            'unit_temperature' => 'celsius',
            'unit_weight' => 'imperial',
        ])->assertSessionHasNoErrors();

        $this->assertSame('metric', Setting::get('unit_distance'));
        $this->assertSame('USD', Setting::get('currency_code'));
    }

    public function test_an_unknown_region_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/format', ['apply_region' => 'atlantis'])
            ->assertSessionHasErrors('apply_region');
    }

    public function test_an_invalid_value_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/format', [
                'currency_code' => 'EUR',
                'currency_position' => 'sideways',
                'number_format' => 'dot_comma',
                'date_format' => 'dmy_dot',
                'time_format' => '24h',
                'unit_distance' => 'metric',
                'unit_temperature' => 'celsius',
                'unit_weight' => 'metric',
            ])
            ->assertSessionHasErrors('currency_position');
    }

    public function test_a_non_admin_is_refused(): void
    {
        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);
        $driver->assignRole('driver');

        // The whole /admin prefix sits behind the `admin` middleware
        // (EnsureAdmin), which redirects non-admins to the dashboard before
        // any controller Gate::authorize() runs — see
        // RolesAndPermissionsTest::test_ensure_admin_redirects_non_admin,
        // which proves the same for admin.dashboard. A driver can never see
        // a 403 on an /admin/* route in this app; they never reach the Gate.
        $this->actingAs($driver->fresh())
            ->get('/admin/settings/format')
            ->assertRedirect(route('dashboard'));
    }
}
