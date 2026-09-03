<?php

namespace App\Services\Weather;

use App\Enums\WeatherMoment;
use App\Events\WeatherSnapshotCreated;
use App\Models\WeatherSnapshot;
use Carbon\CarbonInterface;

/**
 * Die eine Stelle, die entscheidet, welches Wetter zu einem Einsatz gehört.
 *
 * Vorher taten das FetchWeather::handle() und
 * JobLifecycleService::fetchWeatherSync() jeder für sich, mit fast gleichem
 * Rumpf — und beide fragten immer nach dem Wetter von *jetzt*. Bei einem
 * nacherfaßten Einsatz stempelte das dem Nachweis das Wetter des Erfassungs-
 * tages auf, was schlechter ist als eine Lücke: eine Lücke sieht man.
 */
class WeatherFetcher
{
    /**
     * Wie frisch ein Zeitpunkt sein darf, um noch als „jetzt" zu gelten.
     * Das Archiv ist stundenweise aufgelöst; unterhalb einer Stunde ist die
     * Live-Messung genauer und billiger, und laufende Einsätze verhalten sich
     * damit unverändert.
     */
    private const FRESH_WINDOW_MINUTES = 60;

    public function __construct(
        private readonly WeatherProviderRegistry $registry,
    ) {}

    public function fetch(float $lat, float $lon, ?CarbonInterface $at = null): ?WeatherData
    {
        $provider = $this->registry->resolve();

        if (! $this->isPast($at)) {
            return $provider->fetchCurrent($lat, $lon);
        }

        if (! $provider instanceof ArchiveWeatherProviderInterface) {
            // Kein Archiv: lieber gar kein Wetter als das von heute. Die
            // vorhandene Datenqualitätswarnung „Wetter fehlt" greift dann.
            return null;
        }

        return $provider->fetchAt($lat, $lon, $at);
    }

    /**
     * Holt das Wetter und schreibt den Schnappschuß.
     *
     * $force überschreibt einen bereits geholten Schnappschuß — das ist der
     * Wiederholen-Knopf im Admin. Ohne das Flag bleibt Vorhandenes stehen,
     * damit ein zweiter Queue-Anlauf einen guten Wert nicht ersetzt.
     */
    public function record(
        int $jobId,
        WeatherMoment $moment,
        float $lat,
        float $lon,
        ?CarbonInterface $at = null,
        bool $force = false,
    ): ?WeatherSnapshot {
        $existing = WeatherSnapshot::where('job_id', $jobId)
            ->where('moment', $moment)
            ->first();

        if (! $force && $existing !== null && $existing->fetched_at !== null) {
            return null;
        }

        $result = $this->fetch($lat, $lon, $at);

        if ($result === null) {
            return null;
        }

        $snapshot = WeatherSnapshot::updateOrCreate(
            [
                'job_id' => $jobId,
                'moment' => $moment,
            ],
            [
                'provider' => $result->provider,
                'temperature' => $result->temperature_c,
                'precipitation' => $result->precipitation_mm,
                'snow_depth' => $result->snow_depth_cm,
                'wind_speed' => $result->wind_kmh,
                'humidity' => $result->humidity_percent,
                'weather_code' => $result->weather_code,
                'raw_response' => $result->toArray(),
                'fetched_at' => now(),
            ]
        );

        WeatherSnapshotCreated::dispatch($snapshot);

        return $snapshot;
    }

    /**
     * Ob für diesen Zeitpunkt beim eingestellten Anbieter überhaupt eine
     * Abfrage möglich ist.
     *
     * record() liefert null für drei verschiedene Dinge: einen schon
     * vorhandenen Schnappschuß, einen Anbieter ohne Archiv und einen echten
     * Fehlschlag. Der mittlere Fall ist erwartet und deterministisch —
     * Wiederholen ändert daran nichts. Der Aufrufer braucht ihn darum
     * unterscheidbar; das Frischefenster bleibt dabei an der einen Stelle,
     * an der es ohnehin steht.
     */
    public function canFetch(?CarbonInterface $at = null): bool
    {
        if (! $this->isPast($at)) {
            return true;
        }

        return $this->registry->resolve() instanceof ArchiveWeatherProviderInterface;
    }

    private function isPast(?CarbonInterface $at): bool
    {
        return $at !== null && $at->lessThan(now()->subMinutes(self::FRESH_WINDOW_MINUTES));
    }
}
