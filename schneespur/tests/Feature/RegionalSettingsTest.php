<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Format\Formatter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegionalSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    // setUp/tearDown/admin() wörtlich wie in tests/Feature/FormatSettingsTest.php:
    // Web-Routen-Tests brauchen eine installed.lock, und es gibt keine Factories.
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

    private function formatter(array $settings): Formatter
    {
        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }

        app()->forgetInstance(Formatter::class);

        return app(Formatter::class);
    }

    public function test_the_formatter_reads_the_three_new_settings(): void
    {
        $f = $this->formatter([
            'address_format' => 'street_city_state_zip',
            'csv_delimiter' => ',',
            'week_starts_on' => '7',
        ]);

        $this->assertSame('street_city_state_zip', $f->addressFormat());
        $this->assertSame(',', $f->csvDelimiter());
        $this->assertSame(7, $f->weekStartsOn());
    }

    /**
     * Eine Installation, die vor 1.2.1 eingerichtet wurde, hat die drei
     * Schlüssel nicht. Sie darf deswegen nicht in einen leeren String oder
     * eine 0 als Wochenanfang laufen — der Kalender rechnete sonst mit einem
     * Tag, den es nicht gibt.
     */
    public function test_missing_settings_fall_back_to_the_german_defaults(): void
    {
        Setting::whereIn('key', ['address_format', 'csv_delimiter', 'week_starts_on'])->delete();
        app()->forgetInstance(Formatter::class);

        $f = app(Formatter::class);

        $this->assertSame('street_zip_city', $f->addressFormat());
        $this->assertSame(';', $f->csvDelimiter());
        $this->assertSame(1, $f->weekStartsOn());
    }

    public function test_applying_a_region_template_writes_the_three_new_settings(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/format', ['apply_region' => 'us'])
            ->assertRedirect();

        $this->assertSame('street_city_state_zip', Setting::get('address_format'));
        $this->assertSame(',', Setting::get('csv_delimiter'));
        $this->assertSame('7', Setting::get('week_starts_on'));
    }

    public function test_the_three_settings_can_be_saved_individually(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/format', [
                'currency_code' => 'EUR',
                'currency_position' => 'after',
                'number_format' => 'dot_comma',
                'date_format' => 'dmy_dot',
                'time_format' => '24h',
                'unit_distance' => 'metric',
                'unit_temperature' => 'celsius',
                'unit_weight' => 'metric',
                'address_format' => 'street_city_zip',
                'csv_delimiter' => ',',
                'week_starts_on' => '7',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('street_city_zip', Setting::get('address_format'));
        $this->assertSame(',', Setting::get('csv_delimiter'));
        $this->assertSame('7', Setting::get('week_starts_on'));
    }

    public function test_an_unknown_address_format_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/format', [
                'currency_code' => 'EUR',
                'currency_position' => 'after',
                'number_format' => 'dot_comma',
                'date_format' => 'dmy_dot',
                'time_format' => '24h',
                'unit_distance' => 'metric',
                'unit_temperature' => 'celsius',
                'unit_weight' => 'metric',
                'address_format' => 'street_only',
                'csv_delimiter' => ';',
                'week_starts_on' => '1',
            ])
            ->assertSessionHasErrors('address_format');
    }
}
