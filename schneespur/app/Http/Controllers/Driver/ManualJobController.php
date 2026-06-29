<?php

namespace App\Http\Controllers\Driver;

use App\Enums\JobType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\StoreManualJobRequest;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Vehicle;
use App\Services\JobLifecycleService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ManualJobController extends Controller
{
    public function __construct(
        private readonly JobLifecycleService $service,
    ) {}

    public function create(): View
    {
        return view('driver.jobs.manual.create', [
            'customers' => Customer::with('objects')->orderBy('name')->get(),
            'vehicles' => Vehicle::all(),
            'jobTypes' => JobType::cases(),
        ]);
    }

    public function store(StoreManualJobRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $customerObject = CustomerObject::findOrFail($validated['customer_object_id']);
        $vehicle = isset($validated['vehicle_id']) ? Vehicle::find($validated['vehicle_id']) : null;

        $this->service->createManualJob(
            driver: $request->user(),
            customerObject: $customerObject,
            type: $validated['type'],
            startedAt: Carbon::parse($validated['started_at']),
            endedAt: Carbon::parse($validated['ended_at']),
            notes: $validated['notes'] ?? null,
            vehicle: $vehicle,
        );

        return redirect()->route('driver.job.manual.create')
            ->with('success', __('job.manual_created'));
    }
}
