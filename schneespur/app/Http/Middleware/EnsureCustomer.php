<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = auth('customer')->user();

        if (! $customer) {
            return redirect()->route('portal.login');
        }

        if (! $customer->portal_enabled) {
            auth('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login')
                ->with('error', __('portal.account_disabled'));
        }

        if ($customer->locale && app(\App\Services\Extension\LocaleRegistry::class)->has($customer->locale)) {
            \Illuminate\Support\Facades\App::setLocale($customer->locale);
        }

        return $next($request);
    }
}
