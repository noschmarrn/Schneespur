<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Events\User\UserCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDriverRequest;
use App\Http\Requests\Admin\UpdateDriverRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\OwntracksCredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverController extends Controller
{
    public function index(Request $request): View
    {
        $drivers = User::drivers()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.drivers.index', compact('drivers'));
    }

    public function create(): View
    {
        return view('admin.drivers.create', [
            'vehicles' => Vehicle::orderBy('name')->get(),
        ]);
    }

    public function store(StoreDriverRequest $request, OwntracksCredentialService $credentialService): RedirectResponse
    {
        $driver = User::create($request->safe()->only(['name', 'email', 'password', 'phone', 'notes', 'default_vehicle_id']));
        $driver->role = UserRole::Driver;
        $driver->save();
        $driver->assignRole('driver');

        UserCreated::dispatch($driver);

        $credentials = $credentialService->generateCredentials($driver, $request->user());

        session()->flash('owntracks_credentials', $credentials);

        return redirect()
            ->route('admin.drivers.credentials', $driver)
            ->with('success', __('driver.flash_created', ['name' => $driver->name]));
    }

    public function edit(User $driver): View
    {
        return view('admin.drivers.edit', [
            'driver' => $driver,
            'vehicles' => Vehicle::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateDriverRequest $request, User $driver): RedirectResponse
    {
        $driver->update($request->safe()->only(['name', 'email', 'phone', 'notes', 'default_vehicle_id']));

        if ($request->validated('password')) {
            $driver->password = $request->validated('password');
            $driver->save();
        }

        return redirect()
            ->route('admin.drivers.index')
            ->with('success', __('driver.flash_updated', ['name' => $driver->name]));
    }
}
