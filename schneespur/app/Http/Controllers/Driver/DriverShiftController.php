<?php

namespace App\Http\Controllers\Driver;

use App\Enums\LifecyclePoint;
use App\Exceptions\JobLifecycleException;
use App\Http\Controllers\Controller;
use App\Services\Extension\LifecycleFieldRegistry;
use App\Services\JobLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class DriverShiftController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        $registry = app(LifecycleFieldRegistry::class);
        $validated = $request->validate($registry->rules(LifecyclePoint::ShiftStart, $request->user()));
        $extra = Arr::only($validated, $registry->fieldKeys(LifecyclePoint::ShiftStart));

        try {
            app(JobLifecycleService::class)->startShift($request->user(), $extra);
        } catch (JobLifecycleException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('workshift.started'));
    }

    public function end(Request $request): RedirectResponse
    {
        $registry = app(LifecycleFieldRegistry::class);
        $validated = $request->validate($registry->rules(LifecyclePoint::ShiftEnd, $request->user()));
        $extra = Arr::only($validated, $registry->fieldKeys(LifecyclePoint::ShiftEnd));

        try {
            app(JobLifecycleService::class)->endShift($request->user(), $extra);
        } catch (JobLifecycleException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('workshift.ended'));
    }
}
