<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\SeasonService;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PortalDashboardController extends Controller
{
    public function __invoke(SeasonService $seasonService): View
    {
        $customer = auth('customer')->user();
        $season = $seasonService->currentOrLastSeason();

        $totalJobs = Job::where('customer_id', $customer->id)
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$season->start, $season->end])
            ->count();

        $isSqlite = DB::getDriverName() === 'sqlite';
        $durationExpr = $isSqlite
            ? "SUM((JULIANDAY(COALESCE(ended_at, started_at)) - JULIANDAY(started_at)) * 1440)"
            : "SUM(TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, started_at)))";

        $totalMinutes = (int) Job::selectRaw("{$durationExpr} as total_minutes")
            ->where('customer_id', $customer->id)
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$season->start, $season->end])
            ->value('total_minutes');

        $totalHours = number_format($totalMinutes / 60, 1);

        $lastJob = Job::where('customer_id', $customer->id)
            ->whereNotNull('ended_at')
            ->latest('started_at')
            ->first();

        $objects = $customer->objects()
            ->withMax(['serviceJobs as last_job_at' => fn ($q) => $q->whereNotNull('ended_at')], 'started_at')
            ->orderBy('name')
            ->get();

        return view('portal.home', compact('season', 'totalJobs', 'totalHours', 'lastJob', 'objects'));
    }
}
