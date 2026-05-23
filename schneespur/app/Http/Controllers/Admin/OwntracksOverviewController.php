<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GpsPoint;
use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OwntracksOverviewController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('gps.view');

        $drivers = User::drivers()->orderBy('name')->get();
        $driverIds = $drivers->pluck('id');

        $latestGps = collect();
        $activeJobs = collect();

        if ($driverIds->isNotEmpty()) {
            $latestGps = GpsPoint::select('gps_points.*')
                ->whereIn('gps_points.user_id', $driverIds)
                ->joinSub(
                    GpsPoint::select('user_id', DB::raw('MAX(timestamp) as max_ts'))
                        ->whereIn('user_id', $driverIds)
                        ->groupBy('user_id'),
                    'latest',
                    fn ($join) => $join->on('gps_points.user_id', '=', 'latest.user_id')
                        ->on('gps_points.timestamp', '=', 'latest.max_ts')
                )
                ->get()
                ->keyBy('user_id');

            $activeJobs = Job::with('customer')
                ->whereIn('user_id', $driverIds)
                ->whereNull('ended_at')
                ->whereHas('workShift', fn ($q) => $q->whereNull('ended_at'))
                ->get()
                ->keyBy('user_id');
        }

        return view('admin.owntracks.overview', [
            'drivers' => $drivers,
            'latestGps' => $latestGps,
            'activeJobs' => $activeJobs,
            'now' => now()->timestamp,
        ]);
    }
}
