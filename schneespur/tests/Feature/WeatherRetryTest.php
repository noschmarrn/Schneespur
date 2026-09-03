<?php

namespace Tests\Feature;

use App\Enums\WeatherMoment;
use App\Jobs\FetchWeather;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\User;
use App\Models\WeatherSnapshot;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WeatherRetryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Web routes sit behind RedirectToInstaller.
        $lockFile = storage_path('app/installed.lock');
        if (! file_exists($lockFile)) {
            @mkdir(dirname($lockFile), 0755, true);
            file_put_contents($lockFile, 'test');
        }
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/installed.lock'));
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('admin');

        return $admin->fresh();
    }

    private function job(?string $endedAt = '2026-01-15 09:00:00'): Job
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
            'started_at' => '2026-01-15 08:00:00',
            'ended_at' => $endedAt,
        ]);

        return Job::create([
            'work_shift_id' => $shift->id,
            'user_id' => $driver->id,
            'customer_id' => $customer->id,
            'customer_object_id' => $object->id,
            'type' => 'raumen',
            'started_at' => '2026-01-15 08:00:00',
            'ended_at' => $endedAt,
            'is_manual' => true,
        ]);
    }

    /** Der Herzschlag, an dem der WeatherDispatcher den Cron erkennt. */
    private function cronIsRunning(): void
    {
        cache()->put('cron.last_run', now());
    }

    private function fakeArchive(): void
    {
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
            '*' => Http::response([], 200),
        ]);
    }

    public function test_the_retry_button_asks_for_the_moment_of_the_job_and_may_overwrite(): void
    {
        Queue::fake();
        $this->cronIsRunning();

        $job = $this->job();

        $this->actingAs($this->admin())
            ->post(route('admin.jobs.weather-retry', [$job->id, 'start']))
            ->assertRedirect();

        Queue::assertPushed(FetchWeather::class, function (FetchWeather $queued) use ($job) {
            return $queued->jobId === $job->id
                && $queued->moment === WeatherMoment::Start
                && $queued->momentAt?->toDateTimeString() === '2026-01-15 08:00:00'
                && $queued->force === true;
        });
    }

    public function test_the_end_moment_resolves_from_ended_at_not_started_at(): void
    {
        Queue::fake();
        $this->cronIsRunning();

        $job = $this->job();

        $this->actingAs($this->admin())
            ->post(route('admin.jobs.weather-retry', [$job->id, 'end']))
            ->assertRedirect();

        Queue::assertPushed(FetchWeather::class, function (FetchWeather $queued) use ($job) {
            return $queued->jobId === $job->id
                && $queued->moment === WeatherMoment::End
                && $queued->momentAt?->toDateTimeString() === '2026-01-15 09:00:00'
                && $queued->force === true;
        });
    }

    public function test_the_end_moment_on_a_job_that_has_not_ended_is_refused(): void
    {
        Queue::fake();
        $this->cronIsRunning();

        $job = $this->job(endedAt: null);

        $this->actingAs($this->admin())
            ->post(route('admin.jobs.weather-retry', [$job->id, 'end']))
            ->assertRedirect()
            ->assertSessionHas('error');

        Queue::assertNotPushed(FetchWeather::class);
    }

    /**
     * Der Kern von C2: auf gewöhnlichem Webhosting ohne Cron gibt es keinen
     * Arbeiter, der die Warteschlange leert. Ein dispatch() bliebe dort für
     * immer in `jobs` liegen, während dem Betreiber „angestoßen" gemeldet
     * wird — und der Knopf ist ausgerechnet der einzige dokumentierte Weg,
     * einen falschen Wettereintrag richtigzustellen.
     */
    public function test_without_a_running_cron_the_retry_fetches_the_weather_itself(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        Queue::fake();
        cache()->forget('cron.last_run');
        $this->fakeArchive();

        $job = $this->job();

        WeatherSnapshot::create([
            'job_id' => $job->id,
            'moment' => WeatherMoment::End,
            'provider' => 'openmeteo_free',
            'temperature' => 99.0,
            'fetched_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.jobs.weather-retry', [$job->id, 'end']))
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertNotPushed(FetchWeather::class);

        $this->assertEqualsWithDelta(
            -2.8,
            (float) WeatherSnapshot::where('job_id', $job->id)->where('moment', WeatherMoment::End)->value('temperature'),
            0.001,
        );
    }

    /**
     * Und wenn die synchrone Abfrage nichts findet, sagt der Betreiber das
     * auch — „wurde angestoßen" auf eine Abfrage, die gerade leer ausging,
     * wäre bei einem Nachweis die falsche Auskunft.
     */
    public function test_a_synchronous_retry_that_finds_nothing_reports_an_error(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00');
        Queue::fake();
        cache()->forget('cron.last_run');
        Http::fake(['*' => Http::response(['hourly' => ['time' => []]], 200)]);

        $job = $this->job();

        $this->actingAs($this->admin())
            ->post(route('admin.jobs.weather-retry', [$job->id, 'end']))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, WeatherSnapshot::where('job_id', $job->id)->count());
    }

    /**
     * Der Kern von C1: bis hierher stand der Knopf im @else-Zweig von
     * `@if ($snapshot->fetched_at)` — und ein Schnappschuß ohne fetched_at
     * kann gar nicht entstehen, WeatherFetcher::record() setzt es immer. Ein
     * Einsatz MIT Wetter ist genau der Fall, der kaputt war; ein Test nur auf
     * den leeren Fall wäre auch vorher grün gewesen.
     */
    public function test_the_retry_button_is_rendered_on_a_job_that_already_has_weather(): void
    {
        $job = $this->job();

        WeatherSnapshot::create([
            'job_id' => $job->id,
            'moment' => WeatherMoment::End,
            'provider' => 'openmeteo_free',
            'temperature' => -2.8,
            'precipitation' => 0.1,
            'snow_depth' => 16.0,
            'weather_code' => 71,
            'fetched_at' => '2026-01-15 09:00:00',
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.jobs.show', $job))
            ->assertOk()
            ->assertSee(route('admin.jobs.weather-retry', [$job->id, 'end']))
            ->assertSee(__('job.weather_retry_btn'));
    }

    /**
     * Und für einen Zeitpunkt, zu dem gar kein Schnappschuß vorliegt — das
     * Ergebnis eines nacherfaßten Einsatzes bei einem Anbieter ohne Archiv —
     * muß überhaupt erst eine Zeile entstehen, an der der Knopf hängen kann.
     */
    public function test_a_moment_without_a_snapshot_still_gets_a_row_and_a_button(): void
    {
        $job = $this->job();

        $this->actingAs($this->admin())
            ->get(route('admin.jobs.show', $job))
            ->assertOk()
            ->assertSee(__('job.weather_not_fetched'))
            ->assertSee(route('admin.jobs.weather-retry', [$job->id, 'start']))
            ->assertSee(route('admin.jobs.weather-retry', [$job->id, 'end']));
    }

    /**
     * Für das Einsatzende eines laufenden Einsatzes weist der Controller die
     * Abfrage ab; ein Knopf, der nur eine Fehlermeldung erzeugen kann, darf
     * dort nicht stehen.
     */
    public function test_a_running_job_offers_no_end_button(): void
    {
        $job = $this->job(endedAt: null);

        $this->actingAs($this->admin())
            ->get(route('admin.jobs.show', $job))
            ->assertOk()
            ->assertDontSee(route('admin.jobs.weather-retry', [$job->id, 'end']));
    }
}
