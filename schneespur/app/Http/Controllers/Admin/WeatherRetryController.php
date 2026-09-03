<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WeatherMoment;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\Weather\WeatherDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class WeatherRetryController extends Controller
{
    public function __invoke(Job $serviceJob, string $moment): RedirectResponse
    {
        Gate::authorize('jobs.edit');

        $weatherMoment = WeatherMoment::from($moment);

        $object = $serviceJob->customerObject;
        $lat = null;
        $lon = null;

        if ($object !== null && $object->lat !== null && $object->lon !== null) {
            $lat = (float) $object->lat;
            $lon = (float) $object->lon;
        } else {
            $gpsPoint = $serviceJob->gpsPoints()->latest('timestamp')->first();
            if ($gpsPoint !== null && $gpsPoint->lat !== null && $gpsPoint->lon !== null) {
                $lat = (float) $gpsPoint->lat;
                $lon = (float) $gpsPoint->lon;
            }
        }

        if ($lat === null || $lon === null) {
            return redirect()->back()->with('error', __('weather.retry_no_coordinates'));
        }

        // Ohne ended_at würde der Zweig unten auf "jetzt" zurückfallen und mit
        // force=true genau den falschen Schnappschuß erzeugen, den diese
        // Aufgabe eigentlich verhindern soll.
        if ($weatherMoment === WeatherMoment::End && $serviceJob->ended_at === null) {
            return redirect()->back()->with('error', __('weather.retry_job_not_ended'));
        }

        // Der Zeitpunkt, um dessen Wetter es geht — sonst holt der Knopf das
        // Wetter des Tages, an dem er gedrückt wurde. force, weil bei einem
        // nacherfaßten Einsatz genau der falsche Schnappschuß bereits liegt
        // und der Auftrag ihn sonst als „schon erledigt" stehen ließe.
        $momentAt = $weatherMoment === WeatherMoment::End
            ? $serviceJob->ended_at
            : $serviceJob->started_at;

        // Nicht FetchWeather::dispatch(): ohne laufenden Cron gibt es keinen
        // Warteschlangen-Arbeiter, und die Zeile bliebe für immer in `jobs`
        // liegen, während hier „angestoßen" gemeldet wird. Gewöhnliches
        // Webhosting ohne Cron ist das harte Zielbild dieses Projekts.
        $dispatcher = app(WeatherDispatcher::class);

        if ($dispatcher->isCronActive()) {
            $dispatcher->dispatch($serviceJob->id, $weatherMoment, $lat, $lon, $momentAt, true);

            return redirect()->back()->with('success', __('weather.retry_dispatched'));
        }

        // Synchron geholt heißt: das Ergebnis steht schon fest, und der
        // Betreiber erfährt es. Ein „wurde angestoßen" auf eine Abfrage, die
        // gerade nichts gefunden hat, wäre bei einem Nachweis die falsche
        // Auskunft.
        $recorded = $dispatcher->dispatch($serviceJob->id, $weatherMoment, $lat, $lon, $momentAt, true);

        return $recorded
            ? redirect()->back()->with('success', __('weather.retry_fetched'))
            : redirect()->back()->with('error', __('weather.retry_failed'));
    }
}
