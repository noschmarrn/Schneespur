<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Weather\MetNorwayProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The weather contact address is sent as an outbound User-Agent header
 * (MetNorwayProvider::buildUserAgent), so a CR or LF in it is a header
 * injection (M018/S05).
 *
 * Two layers, because either one alone can be undone by an unrelated change:
 * the settings form strips separators before storing, and the provider strips
 * them again before they reach the wire — whatever else writes that setting.
 */
class WeatherUserAgentHeaderTest extends TestCase
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
        // No model factories in this project — create the row directly.
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('admin');

        return $user->fresh();
    }

    public function test_crlf_never_reaches_the_stored_contact_value(): void
    {
        $this->actingAs($this->admin())->post('/admin/settings/weather', [
            'weather_provider' => 'met_norway',
            'weather_user_agent_email' => "x@y.com\r\nX-Test: injected",
            'weather_cache_ttl' => 5,
        ]);

        $stored = (string) Setting::get('weather_user_agent_email', '');

        $this->assertStringNotContainsString("\r", $stored);
        $this->assertStringNotContainsString("\n", $stored);
        $this->assertStringNotContainsString('X-Test', $stored);
    }

    public function test_a_normal_address_is_stored_unchanged(): void
    {
        $this->actingAs($this->admin())->post('/admin/settings/weather', [
            'weather_provider' => 'met_norway',
            'weather_user_agent_email' => 'kontakt@example.test',
            'weather_cache_ttl' => 5,
        ]);

        $this->assertSame('kontakt@example.test', (string) Setting::get('weather_user_agent_email'));
    }

    /**
     * The layer that actually protects the wire: the settings form is not the
     * only writer of this setting, and the 'email' rule guarding it today could
     * be loosened tomorrow without anyone connecting it to a header.
     */
    public function test_a_poisoned_setting_still_produces_a_clean_outbound_header(): void
    {
        Setting::set('weather_user_agent_email', "x@y.com\r\nX-Injected: yes");

        Http::fake([
            'api.met.no/*' => Http::response(['properties' => ['timeseries' => []]], 200),
        ]);

        app(MetNorwayProvider::class)->fetchCurrent(48.1351, 11.5820);

        Http::assertSent(function ($request) {
            $userAgent = $request->header('User-Agent')[0] ?? '';

            // The property that matters is that no header SEPARATOR survives.
            // The remaining text ends up as junk inside one header value, which
            // is ugly but not an injection — and it can only get there if
            // something other than the settings form writes the setting.
            $this->assertStringNotContainsString("\r", $userAgent);
            $this->assertStringNotContainsString("\n", $userAgent);

            // Guzzle rejects a header value containing CR/LF outright, so before
            // the fix this request was never sent at all: a poisoned setting
            // turned the weather off instead of raising anything.
            $this->assertStringStartsWith(brand_slug().'/1.0 ', $userAgent);

            return true;
        });
    }
}
