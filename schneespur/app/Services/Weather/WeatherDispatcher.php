<?php

namespace App\Services\Weather;

use App\Enums\WeatherMoment;
use App\Jobs\FetchWeather;
use Carbon\CarbonInterface;

/**
 * Die eine Stelle, die entscheidet, *wie* eine Wetterabfrage ausgeführt wird.
 *
 * Auf gewöhnlichem Webhosting gibt es womöglich gar keinen Cron und damit
 * keinen Warteschlangen-Arbeiter. Ein FetchWeather::dispatch() bliebe dort für
 * immer in der Tabelle `jobs` liegen, während der Aufrufer meldet, die Abfrage
 * sei angestoßen worden. Deshalb prüft diese Klasse zuerst den Cron-Herzschlag
 * und holt das Wetter sonst gleich hier, im Web-Request.
 *
 * Vorher stand diese Prüfung privat im JobLifecycleService; der
 * Wiederholen-Knopf im Admin kam nicht an sie heran und stellte darum immer
 * bloß in die Warteschlange — auf einer Installation ohne Cron also: nichts.
 */
class WeatherDispatcher
{
    /**
     * Wie alt der letzte Herzschlag sein darf, damit der Cron als aktiv gilt.
     */
    private const HEARTBEAT_MINUTES = 5;

    /**
     * Stößt die Abfrage an — über die Warteschlange, wenn ein Arbeiter sie
     * abholen kann, sonst sofort.
     *
     * @return bool Ob am Ende ein Schnappschuß entstanden ist. Für den
     *              Warteschlangen-Weg ist das unbekannt und darum `true`: der
     *              Auftrag ist wirklich angenommen worden, und ein
     *              Fehlschlag meldet sich dort über FetchWeather::failed().
     */
    public function dispatch(
        int $jobId,
        WeatherMoment $moment,
        float $lat,
        float $lon,
        ?CarbonInterface $at = null,
        bool $force = false,
    ): bool {
        if ($this->isCronActive()) {
            FetchWeather::dispatch($jobId, $moment, $lat, $lon, $at, $force);

            return true;
        }

        return $this->fetchSync($jobId, $moment, $lat, $lon, $at, $force);
    }

    public function isCronActive(): bool
    {
        $lastRun = cache()->get('cron.last_run');

        return $lastRun && $lastRun->diffInMinutes(now()) < self::HEARTBEAT_MINUTES;
    }

    private function fetchSync(
        int $jobId,
        WeatherMoment $moment,
        float $lat,
        float $lon,
        ?CarbonInterface $at,
        bool $force,
    ): bool {
        try {
            return app(WeatherFetcher::class)->record($jobId, $moment, $lat, $lon, $at, $force) !== null;
        } catch (\Throwable) {
            // Wetter blockiert nie einen Einsatz — der Rückgabewert sagt dem
            // Aufrufer, daß nichts entstanden ist.
            return false;
        }
    }
}
