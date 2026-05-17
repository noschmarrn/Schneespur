<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\User;
use App\Models\WeatherSnapshot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function daily(Request $request): View
    {
        $date = $this->parseDate($request->input('date'));
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->addDay()->startOfDay();

        $jobs = Job::with(['customer', 'customerObject.customer', 'user' => fn ($q) => $q->withAnonymized()])
            ->where('started_at', '>=', $dayStart)
            ->where('started_at', '<', $dayEnd)
            ->orderBy('started_at')
            ->get();

        $driverSummaries = $jobs->groupBy('user_id')->map(function ($driverJobs) {
            $user = $driverJobs->first()->user;
            $totalMinutes = $driverJobs->sum(function ($job) {
                if (!$job->ended_at) {
                    return 0;
                }
                return $job->started_at->diffInMinutes($job->ended_at);
            });
            $typeCounts = $driverJobs->groupBy(fn ($j) => $j->type->value)->map->count();

            return (object) [
                'user' => $user,
                'jobs' => $driverJobs,
                'job_count' => $driverJobs->count(),
                'total_minutes' => $totalMinutes,
                'type_counts' => $typeCounts,
            ];
        });

        $totalJobs = $jobs->count();
        $totalMinutes = $driverSummaries->sum('total_minutes');
        $jobTypeBreakdown = $jobs->groupBy(fn ($j) => $j->type->value)->map->count();

        $weatherSummary = $this->buildWeatherSummary($jobs);

        $lastJobDate = null;
        if ($totalJobs === 0) {
            $lastStarted = Job::orderByDesc('started_at')->value('started_at');
            $lastJobDate = $lastStarted ? Carbon::parse($lastStarted)->startOfDay() : null;
        }

        return view('admin.overview.daily', [
            'date' => $date,
            'driverSummaries' => $driverSummaries,
            'totalJobs' => $totalJobs,
            'totalMinutes' => $totalMinutes,
            'jobTypeBreakdown' => $jobTypeBreakdown,
            'weatherSummary' => $weatherSummary,
            'lastJobDate' => $lastJobDate,
            'prevDate' => $date->copy()->subDay(),
            'nextDate' => $date->copy()->addDay(),
        ]);
    }

    public function monthly(Request $request): View
    {
        $month = $this->parseMonth($request->input('month'));
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth()->addDay()->startOfDay();

        $isSqlite = DB::getDriverName() === 'sqlite';
        $durationExpr = $isSqlite
            ? "SUM((JULIANDAY(COALESCE(ended_at, started_at)) - JULIANDAY(started_at)) * 1440)"
            : "SUM(TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, started_at)))";

        $dailyCounts = Job::select(
            DB::raw('DATE(started_at) as job_date'),
            DB::raw('COUNT(*) as job_count'),
            DB::raw("{$durationExpr} as total_minutes")
        )
            ->where('started_at', '>=', $monthStart)
            ->where('started_at', '<', $monthEnd)
            ->groupBy(DB::raw('DATE(started_at)'))
            ->get()
            ->keyBy('job_date');

        $monthTotal = $dailyCounts->sum('job_count');
        $totalMinutes = (int) $dailyCounts->sum('total_minutes');

        $activeDriverCount = Job::where('started_at', '>=', $monthStart)
            ->where('started_at', '<', $monthEnd)
            ->distinct('user_id')
            ->count('user_id');

        $monthKeyExpr = $isSqlite
            ? "strftime('%Y-%m', started_at)"
            : "DATE_FORMAT(started_at, '%Y-%m')";

        $activeMonths = Job::select(
            DB::raw("{$monthKeyExpr} as month_key"),
            DB::raw('COUNT(*) as job_count')
        )
            ->groupBy('month_key')
            ->orderByDesc('month_key')
            ->get()
            ->keyBy('month_key');

        return view('admin.overview.monthly', [
            'month' => $month,
            'dailyCounts' => $dailyCounts,
            'monthTotal' => $monthTotal,
            'totalMinutes' => $totalMinutes,
            'activeDriverCount' => $activeDriverCount,
            'activeMonths' => $activeMonths,
            'prevMonth' => $month->copy()->subMonth(),
            'nextMonth' => $month->copy()->addMonth(),
        ]);
    }

    public function dayDetail(Request $request): View
    {
        $date = $this->parseDate($request->input('date'));
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->addDay()->startOfDay();

        $jobs = Job::with(['customer', 'customerObject.customer', 'user' => fn ($q) => $q->withAnonymized()])
            ->where('started_at', '>=', $dayStart)
            ->where('started_at', '<', $dayEnd)
            ->orderBy('started_at')
            ->get();

        $driverSummaries = $jobs->groupBy('user_id')->map(function ($driverJobs) {
            $user = $driverJobs->first()->user;
            $totalMinutes = $driverJobs->sum(function ($job) {
                if (!$job->ended_at) {
                    return 0;
                }
                return $job->started_at->diffInMinutes($job->ended_at);
            });

            return (object) [
                'user' => $user,
                'jobs' => $driverJobs,
                'job_count' => $driverJobs->count(),
                'total_minutes' => $totalMinutes,
            ];
        });

        $totalJobs = $jobs->count();
        $totalMinutes = $driverSummaries->sum('total_minutes');

        return view('admin.overview.partials.day-detail', [
            'date' => $date,
            'driverSummaries' => $driverSummaries,
            'totalJobs' => $totalJobs,
            'totalMinutes' => $totalMinutes,
            'isInline' => true,
        ]);
    }

    private function parseDate(?string $input): Carbon
    {
        try {
            return $input ? Carbon::parse($input)->startOfDay() : Carbon::today();
        } catch (\Exception) {
            return Carbon::today();
        }
    }

    private function parseMonth(?string $input): Carbon
    {
        try {
            return $input ? Carbon::parse($input)->startOfMonth() : Carbon::today()->startOfMonth();
        } catch (\Exception) {
            return Carbon::today()->startOfMonth();
        }
    }

    private function buildWeatherSummary($jobs): ?object
    {
        $jobIds = $jobs->pluck('id');
        if ($jobIds->isEmpty()) {
            return null;
        }

        $snapshots = WeatherSnapshot::whereIn('job_id', $jobIds)->get();
        if ($snapshots->isEmpty()) {
            return null;
        }

        $temps = $snapshots->pluck('temperature')->filter()->values();
        $hasPrecipitation = $snapshots->contains(fn ($s) => $s->precipitation > 0);

        return (object) [
            'temp_min' => $temps->isNotEmpty() ? $temps->min() : null,
            'temp_max' => $temps->isNotEmpty() ? $temps->max() : null,
            'has_precipitation' => $hasPrecipitation,
        ];
    }
}
