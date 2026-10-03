<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Format\Formatter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Der Wochenanfang war bis hierher halb umgestellt: der Monatskalender folgte
 * der Einstellung, der Knopf „Diese Woche" daneben nicht. Ein US-Betrieb sah
 * einen sonntags beginnenden Kalender neben einem montags beginnenden
 * „This week".
 */
class WeekStartPresetsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ?User $admin = null;

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
        if ($this->admin !== null) {
            return $this->admin;
        }

        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('admin');

        return $this->admin = $user->fresh();
    }

    private function useWeekStart(string $value): void
    {
        Setting::set('week_starts_on', $value);
        app()->forgetInstance(Formatter::class);
    }

    /**
     * Die Falle, wegen der diese Umrechnung überhaupt eine eigene Methode ist:
     * Carbon zählt nicht nach ISO. Carbon::SUNDAY ist 0, nicht 7 — ein direkt
     * durchgereichtes weekStartsOn() wäre stillschweigend falsch.
     */
    public function test_the_iso_week_start_becomes_carbons_own_numbering(): void
    {
        $this->useWeekStart('1');
        $this->assertSame(1, app(Formatter::class)->weekStartsOn());
        $this->assertSame(Carbon::MONDAY, app(Formatter::class)->weekStartsOnCarbon());
        $this->assertSame(1, app(Formatter::class)->weekStartsOnCarbon());

        $this->useWeekStart('7');
        $this->assertSame(7, app(Formatter::class)->weekStartsOn());
        $this->assertSame(Carbon::SUNDAY, app(Formatter::class)->weekStartsOnCarbon());
        // Literal danebengeschrieben, weil genau hier der Fehler säße: 0, nicht 7.
        $this->assertSame(0, app(Formatter::class)->weekStartsOnCarbon());
    }

    /**
     * Mittwoch, 2. September 2026. Die laufende Woche beginnt am Montag,
     * 31.08. — oder am Sonntag, 30.08.
     *
     * @return array{string, string}
     */
    private function weekButtonDates(): array
    {
        return ['2026-08-31', '2026-08-30'];
    }

    private function reportPage(string $route): string
    {
        $this->travelTo(Carbon::parse('2026-09-02 12:00:00'));

        return $this->actingAs($this->admin())
            ->get($route)
            ->assertStatus(200)
            ->getContent();
    }

    public function test_the_driver_report_week_button_follows_the_setting(): void
    {
        [$monday, $sunday] = $this->weekButtonDates();

        $this->useWeekStart('1');
        $html = $this->reportPage('/admin/overview/driver-report');
        $this->assertStringContainsString('from='.$monday, $html);
        $this->assertStringNotContainsString('from='.$sunday, $html);

        $this->useWeekStart('7');
        $html = $this->reportPage('/admin/overview/driver-report');
        $this->assertStringContainsString('from='.$sunday, $html);
        $this->assertStringNotContainsString('from='.$monday, $html);
    }

    public function test_the_customer_report_week_button_follows_the_setting(): void
    {
        [$monday, $sunday] = $this->weekButtonDates();

        $this->useWeekStart('1');
        $html = $this->reportPage('/admin/overview/customer-report');
        $this->assertStringContainsString('from='.$monday, $html);
        $this->assertStringNotContainsString('from='.$sunday, $html);

        $this->useWeekStart('7');
        $html = $this->reportPage('/admin/overview/customer-report');
        $this->assertStringContainsString('from='.$sunday, $html);
        $this->assertStringNotContainsString('from='.$monday, $html);
    }

    /**
     * Die dritte Stelle ist JavaScript und wird in tests/js geprüft — aber nur
     * dort, und dort ohne Blade. Diese Seite hier ist die Naht dazwischen: sie
     * belegt, daß die Komponente den Wert überhaupt bis in das Skript
     * durchreicht. Ohne sie wäre der JS-Test grün und der Knopf trotzdem
     * immer montags.
     */
    public function test_the_preset_component_hands_the_week_start_to_the_browser(): void
    {
        $this->useWeekStart('1');
        $this->assertStringContainsString(
            "datePresets('from', 'to', false, 1)",
            Blade::render('<x-date-range-presets />')
        );

        $this->useWeekStart('7');
        $this->assertStringContainsString(
            "datePresets('from', 'to', false, 7)",
            Blade::render('<x-date-range-presets />')
        );
    }
}
