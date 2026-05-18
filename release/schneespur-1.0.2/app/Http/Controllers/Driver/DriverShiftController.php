<?php

namespace App\Http\Controllers\Driver;

use App\Exceptions\JobLifecycleException;
use App\Http\Controllers\Controller;
use App\Services\JobLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DriverShiftController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        try {
            app(JobLifecycleService::class)->startShift($request->user());
        } catch (JobLifecycleException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('workshift.started'));
    }

    public function end(Request $request): RedirectResponse
    {
        try {
            app(JobLifecycleService::class)->endShift($request->user());
        } catch (JobLifecycleException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('workshift.ended'));
    }
}
