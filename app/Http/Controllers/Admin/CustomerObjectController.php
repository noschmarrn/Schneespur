<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerObjectRequest;
use App\Http\Requests\Admin\UpdateCustomerObjectRequest;
use App\Models\Customer;
use App\Models\CustomerObject;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerObjectController extends Controller
{
    public function index(Customer $customer): View
    {
        $objects = $customer->objects()->orderBy('name')->get();

        return view('admin.customer_objects.index', compact('customer', 'objects'));
    }

    public function create(Customer $customer): View
    {
        return view('admin.customer_objects.create', compact('customer'));
    }

    public function store(StoreCustomerObjectRequest $request, Customer $customer): RedirectResponse
    {
        $object = $customer->objects()->create($request->validated());

        return redirect()
            ->route('admin.customers.objects.index', $customer)
            ->with('success', __('customer_object.flash_created', ['name' => $object->name]));
    }

    public function edit(Customer $customer, CustomerObject $object): View
    {
        return view('admin.customer_objects.edit', compact('customer', 'object'));
    }

    public function update(UpdateCustomerObjectRequest $request, Customer $customer, CustomerObject $object): RedirectResponse
    {
        $object->update($request->validated());

        return redirect()
            ->route('admin.customers.objects.index', $customer)
            ->with('success', __('customer_object.flash_updated', ['name' => $object->name]));
    }

    public function destroy(Customer $customer, CustomerObject $object): RedirectResponse
    {
        if ($object->serviceJobs()->exists()) {
            return redirect()
                ->route('admin.customers.objects.index', $customer)
                ->with('error', __('customer_object.flash_delete_has_jobs', ['name' => $object->name]));
        }

        $name = $object->name;
        $object->delete();

        return redirect()
            ->route('admin.customers.objects.index', $customer)
            ->with('success', __('customer_object.flash_deleted', ['name' => $name]));
    }
}
