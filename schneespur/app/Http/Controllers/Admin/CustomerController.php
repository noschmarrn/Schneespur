<?php

namespace App\Http\Controllers\Admin;

use App\Events\Customer\CustomerDeleted;
use App\Events\Customer\CustomerUpdated;
use App\Events\CustomerCreated as CustomerCreatedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\GeocodingService;
use App\Services\NotificationLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('customers.view');

        $customers = Customer::query()
            ->with('objects')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhereHas('objects', fn ($obj) => $obj->where('city', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function create(): View
    {
        Gate::authorize('customers.view');

        return view('admin.customers.create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        Gate::authorize('customers.edit');

        $customer = Customer::create($request->validated());

        CustomerCreatedEvent::dispatch($customer);

        return redirect()
            ->route('admin.customers.index')
            ->with('success', __('customer.flash_created', ['name' => $customer->name]));
    }

    public function edit(Customer $customer): View
    {
        Gate::authorize('customers.view');

        return view('admin.customers.edit', compact('customer'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('customers.edit');

        $customer->update($request->validated());

        CustomerUpdated::dispatch($customer);

        return redirect()
            ->route('admin.customers.index')
            ->with('success', __('customer.flash_updated', ['name' => $customer->name]));
    }

    public function geocode(Request $request, GeocodingService $geocoding): JsonResponse
    {
        Gate::authorize('customers.view');

        $request->validate([
            'street' => ['required', 'string'],
            'zip' => ['required', 'string'],
            'city' => ['required', 'string'],
        ]);

        $result = $geocoding->resolve($request->street, $request->zip, $request->city);

        if ($result) {
            return response()->json($result);
        }

        return response()->json(['error' => __('customer.geocode_failed')], 422);
    }

    public function destroy(Customer $customer, NotificationLogService $notificationLogService): RedirectResponse
    {
        Gate::authorize('customers.delete');

        $name = $customer->name;
        CustomerDeleted::dispatch($customer);
        $notificationLogService->anonymizeForCustomer($customer);
        $customer->delete();

        return redirect()
            ->route('admin.customers.index')
            ->with('success', __('customer.flash_deleted', ['name' => $name]));
    }
}
