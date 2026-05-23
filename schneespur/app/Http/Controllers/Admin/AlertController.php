<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AlertController extends Controller
{
    public function __construct(private AlertService $alertService) {}

    public function index(Request $request)
    {
        Gate::authorize('alerts.view');

        $filters = $request->only(['type', 'date_from', 'date_to', 'status']);
        $counts = $this->alertService->counts();

        $type = $filters['type'] ?? null;
        $alerts = null;

        if ($type && in_array($type, ['missing_gps', 'missing_weather', 'overdue'])) {
            $query = $this->alertService->forType($type, $filters);
            $isResolved = ($filters['status'] ?? null) === 'resolved';

            if ($isResolved) {
                $query->with(['job.customer', 'job.user', 'resolvedBy']);
            } else {
                $query->with(['customer', 'user']);
            }

            $alerts = $query->paginate(15)->withQueryString();
        }

        return view('admin.alerts.index', [
            'alerts' => $alerts,
            'counts' => $counts,
            'filters' => $filters,
        ]);
    }

    public function resolve(Request $request, Job $serviceJob)
    {
        Gate::authorize('alerts.resolve');

        $validated = $request->validate([
            'alert_type' => 'required|in:missing_gps,missing_weather,overdue',
            'note' => 'nullable|string|max:1000',
        ]);

        $this->alertService->resolve(
            $serviceJob->id,
            $validated['alert_type'],
            $validated['note'] ?? null,
            $request->user()->id,
        );

        return redirect()->back()->with('success', __('alerts.resolved'));
    }

    public function bulkResolve(Request $request)
    {
        Gate::authorize('alerts.resolve');

        $validated = $request->validate([
            'type' => 'required|in:missing_gps,missing_weather,overdue',
        ]);

        $count = $this->alertService->bulkResolve(
            $validated['type'],
            $request->user()->id,
        );

        return redirect()->back()->with('success', __('alerts.bulk_resolved', ['count' => $count]));
    }
}
