<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WeatherMoment;
use App\Http\Controllers\Controller;
use App\Jobs\FetchWeather;
use App\Models\Job;
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

        // Der Zeitpunkt, um dessen Wetter es geht — sonst holt der Knopf das
        // Wetter des Tages, an dem er gedrückt wurde. force, weil bei einem
        // nacherfaßten Einsatz genau der falsche Schnappschuß bereits liegt
        // und der Auftrag ihn sonst als „schon erledigt" stehen ließe.
        $momentAt = $weatherMoment === WeatherMoment::End
            ? $serviceJob->ended_at
            : $serviceJob->started_at;

        FetchWeather::dispatch($serviceJob->id, $weatherMoment, $lat, $lon, $momentAt, true);

        return redirect()->back()->with('success', __('weather.retry_dispatched'));
    }
}
