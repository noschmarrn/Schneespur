<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCustomerReportEmail;
use App\Models\Customer;
use App\Models\CustomerObject;
use App\Services\NotificationLogService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerReportEmailController extends Controller
{
    public function send(Request $request, NotificationLogService $notificationLogService): RedirectResponse
    {
        Gate::authorize('reports.view');

        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'customer_object_id' => ['nullable', 'exists:customer_objects,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $object = isset($validated['customer_object_id'])
            ? CustomerObject::findOrFail($validated['customer_object_id'])
            : null;

        $recipient = $object
            ? ($object->contact_email ?? $customer->notification_email ?? $customer->email)
            : ($customer->notification_email ?? $customer->email);

        if (empty($recipient)) {
            return redirect()->back()->with('error', __('notification.customer_report_email_no_email'));
        }

        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);

        if ($notificationLogService->wasRecentlySentToCustomer($customer, 'customer_report_email', $from, $to)) {
            return redirect()->back()->with('error', __('notification.customer_report_email_duplicate'));
        }

        SendCustomerReportEmail::dispatch($customer, $from, $to, $object);

        return redirect()->back()->with('success', __('notification.customer_report_email_sent'));
    }
}
