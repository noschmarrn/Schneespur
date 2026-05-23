<?php

namespace App\Http\Controllers\Admin;

use App\Enums\JobType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManualJobRequest;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobLifecycleService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ManualJobController extends Controller
{
    public function __construct(
        private readonly JobLifecycleService $service,
    ) {}

    public function create(): View
    {
        Gate::authorize('jobs.edit');

        return view('admin.jobs.manual.create', [
            'customers' => Customer::with('objects')->orderBy('name')->get(),
            'drivers' => User::drivers()->get(),
            'vehicles' => Vehicle::all(),
            'jobTypes' => JobType::cases(),
        ]);
    }

    public function store(StoreManualJobRequest $request): RedirectResponse
    {
        Gate::authorize('jobs.edit');

        $validated = $request->validated();

        $driver = User::findOrFail($validated['user_id']);
        $customerObject = CustomerObject::findOrFail($validated['customer_object_id']);
        $vehicle = isset($validated['vehicle_id']) ? Vehicle::find($validated['vehicle_id']) : null;

        $this->service->createManualJob(
            driver: $driver,
            customerObject: $customerObject,
            type: JobType::from($validated['type']),
            startedAt: Carbon::parse($validated['started_at']),
            endedAt: Carbon::parse($validated['ended_at']),
            notes: $validated['notes'] ?? null,
            vehicle: $vehicle,
        );

        return redirect()->route('admin.jobs.manual.create')
            ->with('success', __('job.manual_created'));
    }
}
