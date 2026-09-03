<?php

namespace Tests\Feature;

use App\Enums\WeatherMoment;
use App\Events\JobCompleted;
use App\Jobs\FetchWeather;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\Setting;
use App\Models\User;
use App\Models\WeatherSnapshot;
use App\Models\WorkShift;
use App\Services\JobLifecycleService;
use App\Services\Weather\WeatherFetcher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherArchiveDispatchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function job(string $startedAt, string $endedAt): Job
    {
        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create(['name' => 'Test Customer']);

        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Test Object',
            'lat' => 48.57,
            'lon' => 13.46,
        ]);

        $shift = WorkShift::create([
            'user_id' => $driver->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);

        return Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'is_manual' => true,
        ]);
    }

    private function fakeArchive(): void
    {
        Http::fake([
            // Drei Stunden in einer Reihe: Beginn und Ende des Januar-
            // Einsatzes und die Stunde, die die Grenzwert-Tests knapp
            // jenseits des Frischefensters treffen. parseArchiveResponse
            // sucht die verlangte Stunde exakt heraus, die übrigen stören
            // also nicht.
            'archive-api.open-meteo.com/*' => Http::response([
                'hourly' => [
                    'time' => ['2026-01-15T08:00', '2026-01-15T09:00', '2026-09-03T10:00'],
                    'temperature_2m' => [-3.2, -2.8, 7.7],
                    'precipitation' => [0.4, 0.1, 0.0],
                    'snow_depth' => [0.15, 0.16, 0.0],
                    'weather_code' => [71, 71, 3],
                    'wind_speed_10m' => [12.0, 11.0, 4.0],
                    'relative_humidity_2m' => [92, 90, 55],
                ],
            ]),
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'time' => '2026-09-03T12:00',
                    'temperature_2m' => 24.7,
                    'precipitation' => 0.0,
                    'snow_depth' => 0.0,
                    'weather_code' => 3,
                    'wind_speed_10m' => 5.0,
                    'relative_humidity_2m' => 40,
                ],
            ]),
        ]);
    }

    public function test_a_past_moment_is_read_from_the_archive_not_from_today(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        $this->fakeArchive();

        $job = $this->job('2026-01-15 08:00:00', '2026-01-15 09:00:00');

        app(WeatherFetcher::class)->record(
            $job->id,
            WeatherMoment::Start,
            48.57,
            13.46,
            Carbon::parse('2026-01-15 08:00:00', 'UTC'),
        );

        $snapshot = WeatherSnapshot::where('job_id', $job->id)->first();

        $this->assertNotNull($snapshot);
        $this->assertEqualsWithDelta(-3.2, (float) $snapshot->temperature, 0.001);
        $this->assertSame(71, $snapshot->weather_code);
        $this->assertEqualsWithDelta(15.0, (float) $snapshot->snow_depth, 0.001);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.open-meteo.com/v1/forecast'));
    }

    public function test_a_moment_that_is_now_still_uses_the_current_reading(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        $this->fakeArchive();

        $job = $this->job('2026-09-03 11:55:00', '2026-09-03 12:00:00');

        app(WeatherFetcher::class)->record(
            $job->id,
            WeatherMoment::End,
            48.57,
            13.46,
            Carbon::parse('2026-09-03 12:00:00', 'UTC'),
        );

        $snapshot = WeatherSnapshot::where('job_id', $job->id)->first();

        $this->assertEqualsWithDelta(24.7, (float) $snapshot->temperature, 0.001);

        // Spiegelbild zur Behauptung in Test 1: für einen laufenden Einsatz
        // bleibt das Archiv unberührt.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'archive-api.open-meteo.com'));
    }

    public function test_a_provider_without_an_archive_leaves_the_weather_empty_rather_than_wrong(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        Setting::set('weather_provider', 'met_norway');
        Http::fake(['*' => Http::response([], 200)]);

        $job = $this->job('2026-01-15 08:00:00', '2026-01-15 09:00:00');

        $result = app(WeatherFetcher::class)->record(
            $job->id,
            WeatherMoment::Start,
            48.57,
            13.46,
            Carbon::parse('2026-01-15 08:00:00', 'UTC'),
        );

        $this->assertNull($result);
        $this->assertSame(0, WeatherSnapshot::where('job_id', $job->id)->count());
        Http::assertNothingSent();
    }

    public function test_an_existing_snapshot_is_kept_unless_the_caller_forces_it(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        $this->fakeArchive();

        $job = $this->job('2026-01-15 08:00:00', '2026-01-15 09:00:00');

        WeatherSnapshot::create([
            'job_id' => $job->id,
            'moment' => WeatherMoment::Start,
            'provider' => 'openmeteo_free',
            'temperature' => 99.0,
            'fetched_at' => now(),
        ]);

        $fetcher = app(WeatherFetcher::class);
        $at = Carbon::parse('2026-01-15 08:00:00', 'UTC');

        $this->assertNull($fetcher->record($job->id, WeatherMoment::Start, 48.57, 13.46, $at));
        $this->assertEqualsWithDelta(
            99.0,
            (float) WeatherSnapshot::where('job_id', $job->id)->value('temperature'),
            0.001,
        );

        $fetcher->record($job->id, WeatherMoment::Start, 48.57, 13.46, $at, force: true);

        $this->assertEqualsWithDelta(
            -3.2,
            (float) WeatherSnapshot::where('job_id', $job->id)->value('temperature'),
            0.001,
        );
    }

    /**
     * Der eigentliche Defekt, an der Stelle, an der er auftrat: ein im
     * September nacherfaßter Januar-Einsatz. Die vier Tests darüber prüfen
     * den Fetcher; dieser prüft, daß die Aufrufstelle ihm den Zeitpunkt des
     * Einsatzes auch wirklich mitgibt.
     */
    public function test_a_manually_recorded_job_is_stamped_with_the_weather_of_its_own_day(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        Event::fake();

        Http::fake([
            'archive-api.open-meteo.com/*' => Http::response([
                'hourly' => [
                    'time' => ['2026-01-15T08:00', '2026-01-15T09:00'],
                    'temperature_2m' => [-3.2, -2.8],
                    'precipitation' => [0.4, 0.1],
                    'snow_depth' => [0.15, 0.16],
                    'weather_code' => [71, 71],
                    'wind_speed_10m' => [12.0, 11.0],
                    'relative_humidity_2m' => [92, 90],
                ],
            ]),
            'api.open-meteo.com/*' => Http::response([
                'current' => [
                    'time' => '2026-09-03T12:00',
                    'temperature_2m' => 24.7,
                    'precipitation' => 0.0,
                    'snow_depth' => 0.0,
                    'weather_code' => 3,
                    'wind_speed_10m' => 5.0,
                    'relative_humidity_2m' => 40,
                ],
            ]),
        ]);

        $driver = User::create([
            'name' => 'Driver',
            'email' => 'driver@test.local',
            'password' => Hash::make('password'),
        ]);

        $customer = Customer::create(['name' => 'Test Customer']);

        $object = CustomerObject::create([
            'customer_id' => $customer->id,
            'name' => 'Test Object',
            'lat' => 48.57,
            'lon' => 13.46,
        ]);

        $job = app(JobLifecycleService::class)->createManualJob(
            $driver,
            $object,
            'raumen',
            Carbon::parse('2026-01-15 08:00:00', 'UTC'),
            Carbon::parse('2026-01-15 09:00:00', 'UTC'),
        );

        $start = WeatherSnapshot::where('job_id', $job->id)->where('moment', WeatherMoment::Start)->first();
        $end = WeatherSnapshot::where('job_id', $job->id)->where('moment', WeatherMoment::End)->first();

        $this->assertNotNull($start);
        $this->assertNotNull($end);
        $this->assertEqualsWithDelta(-3.2, (float) $start->temperature, 0.001);
        $this->assertEqualsWithDelta(-2.8, (float) $end->temperature, 0.001);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.open-meteo.com/v1/forecast'));
    }

    /**
     * Die Kante des Frischefensters. Sie ist der Grund, weshalb laufende
     * Einsätze sich unverändert verhalten — ein Vertun um eine Minute leitete
     * jeden laufenden Einsatz still ins Archiv um, und keiner der übrigen
     * Tests käme dem nahe genug, um es zu merken.
     */
    public function test_a_moment_just_inside_the_fresh_window_still_reads_live(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        $this->fakeArchive();

        $job = $this->job('2026-09-03 11:01:00', '2026-09-03 11:30:00');

        app(WeatherFetcher::class)->record(
            $job->id,
            WeatherMoment::Start,
            48.57,
            13.46,
            Carbon::parse('2026-09-03 11:01:00', 'UTC'),
        );

        $this->assertEqualsWithDelta(
            24.7,
            (float) WeatherSnapshot::where('job_id', $job->id)->value('temperature'),
            0.001,
        );
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'archive-api.open-meteo.com'));
    }

    public function test_a_moment_just_outside_the_fresh_window_reads_the_archive(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        $this->fakeArchive();

        $job = $this->job('2026-09-03 10:59:00', '2026-09-03 11:30:00');

        app(WeatherFetcher::class)->record(
            $job->id,
            WeatherMoment::Start,
            48.57,
            13.46,
            Carbon::parse('2026-09-03 10:59:00', 'UTC'),
        );

        // 10:59 fällt auf die Archivstunde 10:00 — dort steht 7.7, nicht die
        // 24.7 der Live-Messung.
        $this->assertEqualsWithDelta(
            7.7,
            (float) WeatherSnapshot::where('job_id', $job->id)->value('temperature'),
            0.001,
        );
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.open-meteo.com/v1/forecast'));
    }

    /**
     * Der Warteschlangen-Auftrag steigt bei vorhandenem Schnappschuß aus,
     * BEVOR er JobCompleted feuern könnte. Täte er es doch, bekäme der Kunde
     * bei jedem weiteren Anlauf eine zweite Nachweis-Mail. Ohne diesen Test
     * ließen sich die acht Zeilen in handle() umstellen und die Suite bliebe
     * grün.
     */
    public function test_a_second_queue_attempt_on_an_existing_snapshot_stays_silent(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        $this->fakeArchive();

        $job = $this->job('2026-01-15 08:00:00', '2026-01-15 09:00:00');

        WeatherSnapshot::create([
            'job_id' => $job->id,
            'moment' => WeatherMoment::End,
            'provider' => 'openmeteo_free',
            'temperature' => 99.0,
            'fetched_at' => now(),
        ]);

        Event::fake();

        (new FetchWeather($job->id, WeatherMoment::End, 48.57, 13.46))
            ->handle(app(WeatherFetcher::class));

        Event::assertNotDispatched(JobCompleted::class);
        Http::assertNothingSent();
        $this->assertEqualsWithDelta(
            99.0,
            (float) WeatherSnapshot::where('job_id', $job->id)->value('temperature'),
            0.001,
        );
    }

    /**
     * Und der erzwungene Lauf ersetzt zwar den Schnappschuß, schweigt aber
     * ebenso. $force setzt einzig der Wiederholen-Knopf im Admin; ein Knopf
     * mit der Aufschrift „Wetter erneut holen" darf nicht bei jedem Klick
     * Post an den Kunden auslösen.
     */
    public function test_a_forced_run_replaces_the_snapshot_without_notifying_the_customer(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        $this->fakeArchive();

        $job = $this->job('2026-01-15 08:00:00', '2026-01-15 09:00:00');

        WeatherSnapshot::create([
            'job_id' => $job->id,
            'moment' => WeatherMoment::End,
            'provider' => 'openmeteo_free',
            'temperature' => 99.0,
            'fetched_at' => now(),
        ]);

        Event::fake();

        (new FetchWeather(
            $job->id,
            WeatherMoment::End,
            48.57,
            13.46,
            Carbon::parse('2026-01-15 09:00:00', 'UTC'),
            force: true,
        ))->handle(app(WeatherFetcher::class));

        Event::assertNotDispatched(JobCompleted::class);
        $this->assertEqualsWithDelta(
            -2.8,
            (float) WeatherSnapshot::where('job_id', $job->id)->value('temperature'),
            0.001,
        );
    }

    /**
     * „Dieser Anbieter hat kein Archiv" ist ein erwartetes, deterministisches
     * Ergebnis und kein Fehlschlag. Als RuntimeException kostete es auf einer
     * MET-Norway-Installation bei jedem nacherfaßten Einsatz zwei Versuche,
     * 30 Sekunden Rückfallzeit, eine „permanently failed"-Warnung und ebenso
     * lange Verzug, bis der Kunde seinen Nachweis bekam.
     */
    public function test_a_provider_without_an_archive_completes_the_job_instead_of_failing(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        Setting::set('weather_provider', 'met_norway');
        Http::fake(['*' => Http::response([], 200)]);

        $job = $this->job('2026-01-15 08:00:00', '2026-01-15 09:00:00');

        Event::fake();

        (new FetchWeather(
            $job->id,
            WeatherMoment::End,
            48.57,
            13.46,
            Carbon::parse('2026-01-15 09:00:00', 'UTC'),
        ))->handle(app(WeatherFetcher::class));

        Event::assertDispatched(JobCompleted::class, fn (JobCompleted $event) => $event->weatherAvailable === false);
        Http::assertNothingSent();
    }

    /**
     * Der Gegenbeweis: ein Anbieter MIT Archiv, der für die Stunde nichts
     * hat, ist weiterhin ein Fehlschlag und darf wiederholt werden.
     */
    public function test_a_gap_in_a_real_archive_is_still_a_failure(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        Http::fake(['archive-api.open-meteo.com/*' => Http::response(['hourly' => ['time' => []]])]);

        $job = $this->job('2026-01-15 08:00:00', '2026-01-15 09:00:00');

        Event::fake();

        $this->expectException(\RuntimeException::class);

        (new FetchWeather(
            $job->id,
            WeatherMoment::End,
            48.57,
            13.46,
            Carbon::parse('2026-01-15 09:00:00', 'UTC'),
        ))->handle(app(WeatherFetcher::class));
    }

    /**
     * Und wenn der erzwungene Lauf endgültig scheitert, schweigt er ebenso.
     * failed() hatte den Wächter aus handle() nicht: die Kette
     * force-Wiederholung → record() liefert null → RuntimeException → zwei
     * Versuche → failed() → JobCompleted(weatherAvailable: false) →
     * isWeatherUpdate ist wahr → SendJobCompletedNotification nimmt den
     * Zweig ohne Entdopplung schickte dem Kunden ausgerechnet für den Klick,
     * der nichts gefunden hat, einen neuen Nachweis samt PDF.
     */
    public function test_a_forced_run_that_fails_does_not_notify_the_customer_either(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');

        $job = $this->job('2026-01-15 08:00:00', '2026-01-15 09:00:00');

        Event::fake();

        (new FetchWeather(
            $job->id,
            WeatherMoment::End,
            48.57,
            13.46,
            Carbon::parse('2026-01-15 09:00:00', 'UTC'),
            force: true,
        ))->failed(new \RuntimeException('Weather fetch failed'));

        Event::assertNotDispatched(JobCompleted::class);
    }

    /**
     * Der Gegenbeweis zum Test darüber: ein regulärer, nicht erzwungener
     * Auftrag muß beim endgültigen Fehlschlag weiterhin melden, damit der
     * Kunde seinen Nachweis überhaupt bekommt — nur eben ohne Wetter.
     */
    public function test_an_ordinary_run_that_fails_still_completes_the_job(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');

        $job = $this->job('2026-01-15 08:00:00', '2026-01-15 09:00:00');

        Event::fake();

        (new FetchWeather($job->id, WeatherMoment::End, 48.57, 13.46))
            ->failed(new \RuntimeException('Weather fetch failed'));

        Event::assertDispatched(JobCompleted::class, fn (JobCompleted $event) => $event->weatherAvailable === false);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
