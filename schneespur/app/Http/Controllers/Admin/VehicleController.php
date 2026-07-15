<?php

namespace App\Http\Controllers\Admin;

use App\Events\Vehicle\VehicleCreated;
use App\Events\Vehicle\VehicleDeleted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVehicleRequest;
use App\Http\Requests\Admin\UpdateVehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('vehicles.view');

        $vehicles = Vehicle::query()
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.vehicles.index', compact('vehicles'));
    }

    public function create(): View
    {
        Gate::authorize('vehicles.view');

        return view('admin.vehicles.create');
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        Gate::authorize('vehicles.edit');

        $vehicle = Vehicle::create($request->validated());

        VehicleCreated::dispatch($vehicle);

        return redirect()
            ->route('admin.vehicles.index')
            ->with('success', __('vehicle.flash_created', ['name' => $vehicle->name]));
    }

    public function edit(Vehicle $vehicle): View
    {
        Gate::authorize('vehicles.view');

        return view('admin.vehicles.edit', compact('vehicle'));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('vehicles.edit');

        $vehicle->update($request->validated());

        return redirect()
            ->route('admin.vehicles.index')
            ->with('success', __('vehicle.flash_updated', ['name' => $vehicle->name]));
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('vehicles.delete');

        $name = $vehicle->name;

        VehicleDeleted::dispatch($vehicle);

        $vehicle->delete();

        return redirect()
            ->route('admin.vehicles.index')
            ->with('success', __('vehicle.flash_deleted', ['name' => $name]));
    }
}
