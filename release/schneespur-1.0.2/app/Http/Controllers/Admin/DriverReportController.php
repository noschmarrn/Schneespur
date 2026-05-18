<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\User;
use App\Models\WorkShift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverReportController extends Controller
{
    public function index(Request $request): View
    {
        $drivers = User::withAnonymized()->drivers()->orderBy('name')->get();

        $from = $this->parseDate($request->input('from'), Carbon::now()->startOfMonth());
        $to = $this->parseDate($request->input('to'), Carbon::now()->startOfDay());

        $selectedDriver = null;
        $jobs = null;
        $totalJobs = 0;
        $totalMinutes = 0;
        $customerCount = 0;
        $jobTypeBreakdown = collect();
        $shiftCount = 0;
        $totalShiftMinutes = 0;
        $avgShiftMinutes = 0;

        $driverId = $request->input('driver');

        if ($driverId) {
            $selectedDriver = User::withAnonymized()->find($driverId);

            if ($selectedDriver) {
                $jobs = Job::with(['customer', 'customerObject.customer'])
                    ->where('user_id', $driverId)
                    ->where('started_at', '>=', $from)
                    ->where('started_at', '<', $to->copy()->addDay())
                    ->orderBy('started_at')
                    ->get();

                $totalJobs = $jobs->count();
                $totalMinutes = $jobs->sum(fn ($job) => $job->started_at->diffInMinutes($job->ended_at ?? $job->started_at));
                $customerCount = $jobs->pluck('customer_id')->unique()->count();
                $jobTypeBreakdown = $jobs->groupBy(fn ($j) => $j->type->value)->map->count();

                $shifts = WorkShift::where('user_id', $driverId)
                    ->where('started_at', '>=', $from)
                    ->where('started_at', '<', $to->copy()->addDay())
                    ->get();

                $shiftCount = $shifts->count();
                $totalShiftMinutes = $shifts->sum(fn ($s) => $s->started_at->diffInMinutes($s->ended_at ?? $s->started_at));
                $avgShiftMinutes = $shiftCount > 0 ? intdiv($totalShiftMinutes, $shiftCount) : 0;
            }
        }

        return view('admin.overview.driver-report', [
            'drivers' => $drivers,
            'selectedDriver' => $selectedDriver,
            'from' => $from,
            'to' => $to,
            'quickFilters' => $this->buildQuickFilters(),
            'jobs' => $jobs,
            'totalJobs' => $totalJobs,
            'totalMinutes' => $totalMinutes,
            'customerCount' => $customerCount,
            'jobTypeBreakdown' => $jobTypeBreakdown,
            'shiftCount' => $shiftCount,
            'totalShiftMinutes' => $totalShiftMinutes,
            'avgShiftMinutes' => $avgShiftMinutes,
        ]);
    }

    private function parseDate(?string $input, Carbon $default): Carbon
    {
        try {
            return $input ? Carbon::parse($input)->startOfDay() : $default;
        } catch (\Exception) {
            return $default;
        }
    }

    private function buildQuickFilters(): array
    {
        $now = Carbon::now();

        if ($now->month >= 11) {
            $seasonFrom = Carbon::create($now->year, 11, 1);
            $seasonTo = Carbon::create($now->year + 1, 3, 31);
        } elseif ($now->month <= 3) {
            $seasonFrom = Carbon::create($now->year - 1, 11, 1);
            $seasonTo = Carbon::create($now->year, 3, 31);
        } else {
            $seasonFrom = Carbon::create($now->year - 1, 11, 1);
            $seasonTo = Carbon::create($now->year, 3, 31);
        }

        return [
            'week' => [
                'from' => $now->copy()->startOfWeek()->format('Y-m-d'),
                'to' => $now->format('Y-m-d'),
            ],
            'month' => [
                'from' => $now->copy()->startOfMonth()->format('Y-m-d'),
                'to' => $now->format('Y-m-d'),
            ],
            '30days' => [
                'from' => $now->copy()->subDays(30)->format('Y-m-d'),
                'to' => $now->format('Y-m-d'),
            ],
            'season' => [
                'from' => $seasonFrom->format('Y-m-d'),
                'to' => $seasonTo->format('Y-m-d'),
            ],
        ];
    }
}
