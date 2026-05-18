<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WeatherMoment;
use App\Http\Controllers\Controller;
use App\Jobs\FetchWeather;
use App\Models\Job;
use Illuminate\Http\RedirectResponse;

class WeatherRetryController extends Controller
{
    public function __invoke(Job $serviceJob, string $moment): RedirectResponse
    {
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

        FetchWeather::dispatch($serviceJob->id, $weatherMoment, $lat, $lon);

        return redirect()->back()->with('success', __('weather.retry_dispatched'));
    }
}
