<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PortalCredentialsMail;
use App\Models\Customer;
use App\Services\NotificationLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CustomerPortalController extends Controller
{
    public function setupAccess(Request $request, Customer $customer, NotificationLogService $logService): RedirectResponse
    {
        Gate::authorize('customers.edit');

        if (! $customer->email) {
            return redirect()
                ->route('admin.customers.edit', $customer)
                ->with('error', __('customer.portal_no_email'));
        }

        $isReset = $customer->getOriginal('password') !== null;
        $plainPassword = Str::random(12);

        $customer->password = $plainPassword;
        $customer->portal_enabled = true;
        $customer->save();

        try {
            Mail::to($customer->email)->send(new PortalCredentialsMail($customer, $plainPassword, $isReset));

            $logService->logSentForCustomer($customer, 'portal_credentials', $customer->email, [
                'action' => $isReset ? 'reset' : 'setup',
            ]);

            $flashKey = $isReset ? 'customer.portal_flash_reset' : 'customer.portal_flash_setup';

            return redirect()
                ->route('admin.customers.edit', $customer)
                ->with('success', __($flashKey, ['name' => $customer->name]));
        } catch (\Throwable $e) {
            $logService->logFailedForCustomer($customer, 'portal_credentials', $customer->email, $e->getMessage(), [
                'action' => $isReset ? 'reset' : 'setup',
            ]);

            return redirect()
                ->route('admin.customers.edit', $customer)
                ->with('error', __('customer.portal_flash_email_failed', ['name' => $customer->name]));
        }
    }

    public function updateSettings(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize('customers.edit');

        $validated = $request->validate([
            'portal_enabled' => ['required', 'boolean'],
            'portal_show_gps' => ['required', 'boolean'],
            'portal_show_photos' => ['required', 'boolean'],
            'portal_show_driver_name' => ['required', 'boolean'],
        ]);

        $customer->update($validated);

        return redirect()
            ->route('admin.customers.edit', $customer)
            ->with('success', __('customer.portal_flash_settings_updated', ['name' => $customer->name]));
    }
}
