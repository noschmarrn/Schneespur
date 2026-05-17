<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverJobHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $jobs = Job::where('user_id', $request->user()->id)
            ->with(['customer', 'customerObject.customer'])
            ->withCount('jobPhotos')
            ->orderByDesc('started_at')
            ->paginate(20);

        return view('driver.jobs.index', compact('jobs'));
    }

    public function show(Request $request, Job $job): View
    {
        abort_unless($job->user_id === $request->user()->id, 403);

        $job->load(['customer', 'customerObject.customer', 'vehicle', 'weatherSnapshots', 'jobPhotos'])
            ->loadCount('gpsPoints');

        return view('driver.jobs.show', compact('job'));
    }
}
