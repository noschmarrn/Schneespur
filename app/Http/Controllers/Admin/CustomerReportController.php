<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Job;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerReportController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::orderBy('name')->get();

        $from = $this->parseDate($request->input('from'), Carbon::now()->startOfMonth());
        $to = $this->parseDate($request->input('to'), Carbon::now()->startOfDay());

        $selectedCustomer = null;
        $jobs = null;
        $totalJobs = 0;
        $totalMinutes = 0;
        $driverCount = 0;
        $jobTypeBreakdown = collect();
        $avgDurationMinutes = 0;
        $frequencyPerWeek = null;
        $sammelPdfUrl = null;

        $customerId = $request->input('customer');

        if ($customerId) {
            $selectedCustomer = Customer::find($customerId);

            if ($selectedCustomer) {
                $jobs = Job::with(['customerObject', 'user' => fn ($q) => $q->withAnonymized()])
                    ->where('customer_id', $customerId)
                    ->where('started_at', '>=', $from)
                    ->where('started_at', '<', $to->copy()->addDay())
                    ->orderBy('started_at')
                    ->get();

                $totalJobs = $jobs->count();
                $totalMinutes = $jobs->sum(fn ($job) => $job->started_at->diffInMinutes($job->ended_at ?? $job->started_at));
                $driverCount = $jobs->pluck('user_id')->unique()->count();
                $jobTypeBreakdown = $jobs->groupBy(fn ($j) => $j->type->value)->map->count();
                $avgDurationMinutes = $totalJobs > 0 ? intdiv($totalMinutes, $totalJobs) : 0;

                $days = max(1, $from->diffInDays($to));
                if ($days >= 7) {
                    $weeks = max(1, ceil($days / 7));
                    $frequencyPerWeek = round($totalJobs / $weeks, 1);
                }

                $sammelPdfUrl = route('admin.exports.customer-pdf', [
                    'customer' => $customerId,
                    'from' => $from->format('Y-m-d'),
                    'to' => $to->format('Y-m-d'),
                ]);
            }
        }

        return view('admin.overview.customer-report', [
            'customers' => $customers,
            'selectedCustomer' => $selectedCustomer,
            'from' => $from,
            'to' => $to,
            'quickFilters' => $this->buildQuickFilters(),
            'jobs' => $jobs,
            'totalJobs' => $totalJobs,
            'totalMinutes' => $totalMinutes,
            'driverCount' => $driverCount,
            'jobTypeBreakdown' => $jobTypeBreakdown,
            'avgDurationMinutes' => $avgDurationMinutes,
            'frequencyPerWeek' => $frequencyPerWeek,
            'sammelPdfUrl' => $sammelPdfUrl,
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
