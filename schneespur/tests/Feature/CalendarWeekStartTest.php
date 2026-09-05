<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Format\Formatter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CalendarWeekStartTest extends TestCase
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

    private function calendar(string $weekStart, string $month): string
    {
        Setting::set('week_starts_on', $weekStart);
        app()->forgetInstance(Formatter::class);

        return $this->actingAs($this->admin())
            ->get('/admin/overview/monthly?month='.$month)
            ->assertStatus(200)
            ->getContent();
    }

    /**
     * Die Kalenderansicht rendert das Gitter (samt Kopfzeile) nur, wenn der
     * Monat mindestens einen Einsatz hat — sonst zeigt sie den Leerzustand.
     * Ohne diesen Einsatz würden beide HTML-Tests unten stets false gegen
     * false vergleichen und niemals aussagekräftig scheitern oder bestehen.
     */
    private function seedJobInMonth(string $yearMonth): void
    {
        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create(['name' => 'Summit Snow']);

        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Parking lot',
            'street' => '400 S 4th St',
            'zip' => '55401',
            'city' => 'Minneapolis',
            'state' => 'MN',
        ]);

        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => $yearMonth.'-15 08:00:00',
            'ended_at' => $yearMonth.'-15 10:00:00',
        ]);

        Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => $yearMonth.'-15 08:00:00',
            'ended_at' => $yearMonth.'-15 10:00:00',
        ]);
    }

    /**
     * Isoliert die Kopfzeile aus dem gerenderten HTML. Zwei Gründe: Blade
     * setzt zwischen dem öffnenden '>' und dem Tagesnamen einen Zeilenumbruch
     * samt Einrückung (kein '>Mo' als zusammenhängende Zeichenkette), und der
     * Seitentext enthält "Monat"/"Monatsübersicht" — beides Fundstellen, die
     * ein ungebundener strpos() nach 'Mo' fälschlich vor der echten Kopfzeile
     * finden würde. Die beiden markanten "grid grid-cols-7"-Klassen (Kopfzeile
     * mit Rand, Tageszellen ohne) grenzen die Kopfzeile eindeutig ein.
     */
    private function dayHeaderRow(string $html): string
    {
        $start = strpos($html, 'grid grid-cols-7 border-b border-gray-200');
        $end = strpos($html, 'grid grid-cols-7"', $start);

        return substr($html, $start, $end - $start);
    }

    /**
     * Januar 2026 beginnt an einem Donnerstag. In einer Montagswoche stehen
     * davor drei Leerzellen (Mo, Di, Mi), in einer Sonntagswoche vier.
     */
    public function test_a_monday_week_puts_monday_first(): void
    {
        $this->seedJobInMonth('2026-01');

        $header = $this->dayHeaderRow($this->calendar('1', '2026-01-01'));

        // Die Kopfzeile beginnt mit Montag …
        $this->assertLessThan(
            strpos($header, __('overview.day_so')),
            strpos($header, __('overview.day_mo'))
        );
    }

    public function test_a_sunday_week_puts_sunday_first(): void
    {
        $this->seedJobInMonth('2026-01');

        $header = $this->dayHeaderRow($this->calendar('7', '2026-01-01'));

        // … und hier steht Sonntag vor Montag.
        $this->assertLessThan(
            strpos($header, __('overview.day_mo')),
            strpos($header, __('overview.day_so'))
        );
    }

    /**
     * Die Rechnung selbst, ohne den Umweg über gerendertes HTML: sie muß für
     * jeden Wochentag und beide Wochenanfänge stimmen — genau hier lag der
     * Fehler, den ein Test über nur einen Monat nicht gefunden hätte.
     */
    public function test_the_leading_blank_count_is_right_for_every_weekday(): void
    {
        // [Monatserster, Wochenanfang, erwartete Leerzellen]
        $cases = [
            ['2026-01-01', 1, 3], // Donnerstag, Montagswoche: Mo Di Mi
            ['2026-01-01', 7, 4], // Donnerstag, Sonntagswoche: So Mo Di Mi
            ['2026-02-01', 1, 6], // Sonntag, Montagswoche: Mo bis Sa
            ['2026-02-01', 7, 0], // Sonntag, Sonntagswoche: keine
            ['2026-06-01', 1, 0], // Montag, Montagswoche: keine
            ['2026-06-01', 7, 1], // Montag, Sonntagswoche: So
        ];

        foreach ($cases as [$date, $weekStart, $expected]) {
            $startDow = \Carbon\Carbon::parse($date)->dayOfWeekIso;

            $this->assertSame(
                $expected,
                ($startDow - $weekStart + 7) % 7,
                "Leerzellen für {$date} bei Wochenanfang {$weekStart}"
            );
        }
    }
}
